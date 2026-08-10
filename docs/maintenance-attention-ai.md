# Offline maintenance attention model

PMAMS keeps its deterministic maintenance-attention rules as the authoritative
recommendation. A local Random Forest model is an optional additive signal: it
can increase a device's risk score, but it never removes a rule-based reason,
and a missing/broken Python installation automatically falls back to the normal
Laravel rules.

## XAMPP/Laragon setup

1. Install Python 3.10 or newer on the server.
2. From the PMAMS project directory, install the local-only dependencies:

   ```powershell
   python -m pip install -r ai/requirements.txt
   ```

3. In `.env`, enable the feature and set the Python executable if needed:

   ```dotenv
   MAINTENANCE_AI_ENABLED=true
   MAINTENANCE_AI_AUTO_TRAIN=true
   MAINTENANCE_AI_TRAIN_DAY=1
   MAINTENANCE_AI_TRAIN_TIME=03:30
   MAINTENANCE_AI_MIN_SAMPLES=20
   MAINTENANCE_AI_PYTHON=python
   MAINTENANCE_AI_TIMEOUT=10
   MAINTENANCE_AI_TRAINING_TIMEOUT=60
   MAINTENANCE_AI_CACHE_MINUTES=10
   ```

   If Apache/XAMPP cannot see Python through its service account PATH, set
   `MAINTENANCE_AI_PYTHON` to the absolute executable path, for example
   `C:\\Users\\YourUser\\AppData\\Local\\Programs\\Python\\Python311\\python.exe`.

4. Train a model from the current inventory and the last 12 months of
   checklist/maintenance history:

   ```powershell
   php artisan maintenance:train-model
   ```

   For a small test database only, use `--min-samples=2`. Production training
   should wait until there are enough positive and negative examples.

The model and metadata are written to `storage/app/ai/`. They are local
deployment artifacts and are intentionally excluded from Git. Retrain after
meaningful new maintenance history is collected. No inventory data is sent to
the internet or to an external AI service.

## Automatic retraining

When `MAINTENANCE_AI_AUTO_TRAIN=true`, Laravel runs
`maintenance:train-model` once a month on `MAINTENANCE_AI_TRAIN_DAY` at
`MAINTENANCE_AI_TRAIN_TIME` (default: day 1 at 03:30). The day is limited to
28 so the schedule works in every month. The job uses an overlap lock and runs
after the normal backup schedule. Change the settings in `.env`, then keep the
Laravel scheduler running:

```powershell
php artisan schedule:work
```

On Windows Task Scheduler, run `php artisan schedule:run` every minute instead.
Automatic training is safe to leave enabled before Python is installed: the
job reports the missing dependency and the page continues with deterministic
rules. The next scheduled run will train automatically after dependencies and
enough labeled history are available.

## What is learned

Training uses numeric, auditable signals already used by the page: RAM and
memory availability, HDD storage, equipment type, condition/status, OS and
Office license flags, recent issue count, transfers, equipment age, and
maintenance recency. The label is derived from recent issues and current
attention conditions, so the first model is a bootstrap ranking aid rather than
a replacement for technician review.

If Python, ONNX Runtime, or the model file is unavailable, the Maintenance
Attention page continues to work using the existing local rules only.

## Recommendation engine mode

Super Admins can choose the recommendation source directly on the Maintenance
Attention page:

- **Laravel coded rules only** — deterministic, explainable rules for RAM,
  storage, licensing, age, condition, maintenance age, and transfers.
- **Local AI trained model only** — the local scikit-learn/ONNX classifier
  controls the score and marks a card **AI recommended** at 70% confidence or
  higher. If the model is unavailable, the page safely falls back to Laravel
  rules.
- **Rules + Local AI (recommended)** — keeps the rule explanation and lets the
  local model raise the priority when it detects additional risk.

The selection is stored in `system_settings` under
`maintenance_attention_mode`, so it applies consistently to all users. The
model never calls an external service and condemned equipment remains
excluded.
