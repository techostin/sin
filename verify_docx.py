# -*- coding: utf-8 -*-
"""Verificações automáticas do documento final."""
import re
import sys
from docx import Document

DOC = r"C:\xampp\htdocs\app\Reuniao_09-11-2026_Compilacao_Tematica.docx"
doc = Document(DOC)

paras = doc.paragraphs
tables = doc.tables
print("parágrafos:", len(paras), "| tabelas:", len(tables))

heads = [(p.style.name, p.text) for p in paras if p.style.name.startswith("Heading")]
print("\nTítulos:")
for style, text in heads:
    print("  ", style, "|", text)

print("\nSeções:")
for i, t in enumerate(tables):
    print(f"  [{i}] {len(t.rows)}x{len(t.columns)} 1a célula: {t.rows[0].cells[0].text[:60]!r}")

# texto integral
texts = [p.text for p in paras]
for t in tables:
    for row in t.rows:
        for c in row.cells:
            texts.append(c.text)
full = "\n".join(texts)

words = re.findall(r"[A-Za-zÀ-ÿ]+", full)
print("\ntotal de palavras:", len(words))

EN = {"the", "and", "you", "your", "youre", "we", "they", "that", "this", "with", "for", "from",
      "have", "will", "can", "would", "should", "but", "not", "all", "any", "about", "there",
      "what", "when", "which", "who", "how", "why", "was", "were", "is", "are", "be", "been",
      "wasn", "i", "i'm", "im", "it", "its", "he", "she", "his", "her", "them", "their", "our",
      "my", "me", "us", "do", "does", "did", "done", "going", "just", "like", "know", "think",
      "say", "said", "see", "saw", "get", "got", "make", "made", "take", "took", "good", "bad",
      "yes", "yeah", "okay", "ok", "well", "very", "more", "most", "some", "other", "than",
      "then", "because", "so", "if", "on", "at", "in", "of", "to", "as", "by", "or", "an",
      "wasnt", "dont", "didnt", "cant", "thats", "hes", "shes", "its", "let", "lets", "right",
      "sir", "ma", "am", "please", "sorry", "hello", "hey", "one", "two", "three", "people",
      "thing", "things", "here", "where", "now", "new", "old", "big", "small", "much", "many"}
lower = [w.lower() for w in words]
hits = {}
for w in lower:
    if w in EN:
        hits[w] = hits.get(w, 0) + 1
print("\npalavras potencialmente não-ptBR:", dict(sorted(hits.items(), key=lambda x: -x[1])))

# ocorrências com contexto
for w in hits:
    for m in re.finditer(r"\b" + re.escape(w) + r"\b", full, flags=re.IGNORECASE):
        s = max(0, m.start() - 60); e = min(len(full), m.end() + 60)
        print("   ...", full[s:e].replace("\n", " "), "...")
        break

# checagem de estrutura
must = ["Assessoria à Associação", "Resumo executivo", "Linha do tempo",
        "Encaminhamentos e próximos passos", "Nomes, instituições e lugares citados"]
print("\nchecagem de seções:")
for m in must:
    print("  ", "OK " if m in full else "FALTA", m)

# contagem de caracteres por seção (proxy de tamanho)
print("\ncaracteres totais:", len(full))
print("parágrafos vazios:", sum(1 for p in paras if not p.text.strip()))
