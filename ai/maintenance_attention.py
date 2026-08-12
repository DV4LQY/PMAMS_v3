"""Train and run the optional offline maintenance-attention model.

The Laravel application owns the feature definitions and deterministic rules.
This script only trains/infer a small RandomForest model from JSON over stdin,
so it never requires a network connection or sends inventory outside PMAMS.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from typing import Any


FEATURE_NAMES = [
    "memory_gb",
    "memory_known",
    "has_hdd",
    "is_desktop",
    "is_unserviceable",
    "is_repair",
    "is_not_in_use",
    "os_cracked",
    "ms_office_cracked",
    "recent_issue_count",
    "transfer_count",
    "age_years",
    "maintenance_overdue_days",
    "maintenance_missing",
]

# Must match MaintenanceAttentionService. The values are stored in metadata
# so PHP can reject an artifact trained with an older age policy and safely
# fall back to the visible Laravel rules until retraining is completed.
OLD_EQUIPMENT_AGE_YEARS = 6
RULES_VERSION = "age-threshold-6"


def fail(message: str, code: int = 1) -> int:
    print(json.dumps({"ok": False, "error": message}), file=sys.stderr)
    return code


def read_payload() -> dict[str, Any]:
    raw = sys.stdin.read()
    if not raw.strip():
        raise ValueError("No JSON payload was provided on stdin.")
    payload = json.loads(raw)
    if not isinstance(payload, dict):
        raise ValueError("The JSON payload must be an object.")
    return payload


def vector(row: dict[str, Any]) -> list[float]:
    values: list[float] = []
    for name in FEATURE_NAMES:
        value = row.get(name, 0)
        try:
            values.append(float(value))
        except (TypeError, ValueError):
            values.append(0.0)
    return values


def train(payload: dict[str, Any], model_path: str, metadata_path: str) -> int:
    try:
        import numpy as np
        from sklearn.ensemble import RandomForestClassifier
        from skl2onnx import convert_sklearn
        from skl2onnx.common.data_types import FloatTensorType
    except ImportError as exc:
        return fail(
            "Python AI dependencies are missing. Run `python -m pip install -r ai/requirements.txt`. "
            f"Details: {exc}"
        )

    rows = payload.get("rows", [])
    if not isinstance(rows, list) or len(rows) < 2:
        return fail("At least two labeled rows are required to train the model.")

    features: list[list[float]] = []
    labels: list[int] = []
    for row in rows:
        if not isinstance(row, dict):
            continue
        try:
            label = int(row.get("label", 0))
        except (TypeError, ValueError):
            continue
        features.append(vector(row))
        labels.append(1 if label else 0)

    if len(features) < 2 or len(set(labels)) < 2:
        return fail("Training requires both attention (1) and no-attention (0) examples.")

    x = np.asarray(features, dtype=np.float32)
    y = np.asarray(labels, dtype=np.int64)
    model = RandomForestClassifier(
        n_estimators=min(200, max(50, len(features) * 4)),
        max_depth=8,
        min_samples_leaf=1,
        class_weight="balanced",
        random_state=42,
        n_jobs=1,
    )
    model.fit(x, y)

    os.makedirs(os.path.dirname(model_path), exist_ok=True)
    os.makedirs(os.path.dirname(metadata_path), exist_ok=True)
    onnx_model = convert_sklearn(
        model,
        initial_types=[("features", FloatTensorType([None, len(FEATURE_NAMES)]))],
        target_opset=15,
        options={id(model): {"zipmap": True}},
    )
    with open(model_path, "wb") as handle:
        handle.write(onnx_model.SerializeToString())
    with open(metadata_path, "w", encoding="utf-8") as handle:
        json.dump(
            {
                "feature_names": FEATURE_NAMES,
                "samples": len(features),
                "positive_samples": int(sum(labels)),
                "trained_at": datetime.now(timezone.utc).isoformat(),
                "algorithm": "RandomForestClassifier",
                "rules_version": RULES_VERSION,
                "old_equipment_threshold_years": OLD_EQUIPMENT_AGE_YEARS,
            },
            handle,
            indent=2,
        )

    print(json.dumps({"ok": True, "samples": len(features), "model": model_path}))
    return 0


def probabilities(output: Any, count: int) -> list[float]:
    """Normalize ONNX Runtime's zipmap or matrix probability output."""
    result: list[float] = []
    if isinstance(output, list):
        for item in output:
            if isinstance(item, dict):
                value = item.get(1, item.get("1", item.get(True, 0.0)))
                result.append(float(value))
            else:
                try:
                    values = list(item)
                    result.append(float(values[-1]) if values else 0.0)
                except TypeError:
                    result.append(float(item))
    else:
        array = output.tolist() if hasattr(output, "tolist") else output
        if isinstance(array, list) and array and isinstance(array[0], list):
            result = [float(row[-1]) if row else 0.0 for row in array]
        elif isinstance(array, list):
            result = [float(value) for value in array]
    return [max(0.0, min(1.0, value)) for value in (result + [0.0] * count)[:count]]


def predict(payload: dict[str, Any], model_path: str, metadata_path: str) -> int:
    try:
        import numpy as np
        import onnxruntime as ort
    except ImportError as exc:
        return fail(
            "Python AI dependencies are missing. Run `python -m pip install -r ai/requirements.txt`. "
            f"Details: {exc}"
        )

    rows = payload.get("rows", [])
    if not isinstance(rows, list):
        return fail("The rows value must be an array.")
    if not os.path.isfile(model_path):
        return fail("The trained model file does not exist.")

    try:
        session = ort.InferenceSession(model_path, providers=["CPUExecutionProvider"])
        matrix = np.asarray([vector(row) for row in rows], dtype=np.float32)
        outputs = session.run(None, {session.get_inputs()[0].name: matrix})
        values = probabilities(outputs[1] if len(outputs) > 1 else outputs[0], len(rows))
    except Exception as exc:  # noqa: BLE001 - return a safe, actionable error to Laravel
        return fail(f"Local model inference failed: {exc}")

    print(json.dumps({"ok": True, "predictions": values}))
    return 0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("mode", choices=["train", "predict"])
    parser.add_argument("--model", required=True)
    parser.add_argument("--metadata", required=True)
    args = parser.parse_args()
    try:
        payload = read_payload()
        if args.mode == "train":
            return train(payload, args.model, args.metadata)
        return predict(payload, args.model, args.metadata)
    except (ValueError, json.JSONDecodeError) as exc:
        return fail(str(exc))


if __name__ == "__main__":
    raise SystemExit(main())
