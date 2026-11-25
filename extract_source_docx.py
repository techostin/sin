import sys
from docx import Document

SRC = r"C:\Users\edmil\Downloads\Reunião Zoom de Marcia Riva.docx"
OUT = r"C:\xampp\htdocs\app\source_transcript.txt"

doc = Document(SRC)

lines = []
lines.append("=== PARAGRAPHS ===")
for i, p in enumerate(doc.paragraphs):
    style = p.style.name if p.style is not None else ""
    text = p.text
    lines.append(f"[{i}] <{style}> {text!r}")

lines.append("")
lines.append("=== TABLES ===")
for ti, t in enumerate(doc.tables):
    lines.append(f"--- table {ti}: rows={len(t.rows)} cols={len(t.columns)}")
    for ri, row in enumerate(t.rows):
        cells = [c.text.replace("\n", " | ") for c in row.cells]
        lines.append(f"  r{ri}: " + " || ".join(cells))

lines.append("")
lines.append("=== SECTIONS/HEADERS/FOOTERS ===")
for si, s in enumerate(doc.sections):
    for name in ("header", "footer"):
        part = getattr(s, name)
        txt = "\n".join(p.text for p in part.paragraphs)
        lines.append(f"sec{si} {name}: {txt!r}")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(lines))

print(f"paragraphs={len(doc.paragraphs)} tables={len(doc.tables)} written={OUT}")
