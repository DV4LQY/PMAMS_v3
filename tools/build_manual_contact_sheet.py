from pathlib import Path
from PIL import Image, ImageOps, ImageDraw, ImageFont

src = Path(r"C:\Users\MIS_4LQY_PC\AppData\Local\Temp")
out = Path(r"C:\laragon\www\pms_systemv2\manual_assets\contact-sheet.png")
out.parent.mkdir(parents=True, exist_ok=True)
files = sorted(src.glob("codex-clipboard-*.png"), key=lambda p: p.stat().st_mtime, reverse=True)[:40]
thumb_w, thumb_h = 280, 180
label_h = 36
cols = 4
rows = (len(files) + cols - 1) // cols
sheet = Image.new("RGB", (cols * thumb_w, rows * (thumb_h + label_h)), "#f1f5f9")
draw = ImageDraw.Draw(sheet)
for i, path in enumerate(files):
    try:
        im = Image.open(path).convert("RGB")
        im.thumbnail((thumb_w - 12, thumb_h - 12))
        x = (i % cols) * thumb_w
        y = (i // cols) * (thumb_h + label_h)
        px = x + (thumb_w - im.width) // 2
        py = y + (thumb_h - im.height) // 2
        sheet.paste(im, (px, py))
        name = path.stem.replace("codex-clipboard-", "")[:22]
        draw.text((x + 6, y + thumb_h + 3), name, fill="#0f172a")
    except Exception as exc:
        draw.text(((i % cols) * thumb_w + 6, (i // cols) * (thumb_h + label_h) + 6), str(exc), fill="red")
sheet.save(out)
print(out)
