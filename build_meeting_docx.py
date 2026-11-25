# -*- coding: utf-8 -*-
"""Gera a compilação temática (pt-BR) da reunião de 9 de novembro de 2026."""
import re
from docx import Document
from docx.shared import Pt, Cm, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_TAB_ALIGNMENT
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.section import WD_SECTION
from docx.oxml import OxmlElement
from docx.oxml.ns import qn

OUT = r"C:\xampp\htdocs\app\Reuniao_09-11-2026_Compilacao_Tematica.docx"

GREEN_DARK = RGBColor(0x1F, 0x4E, 0x3D)
GREEN_MID = RGBColor(0x2F, 0x6B, 0x4F)
GREEN_SOFT = RGBColor(0x6E, 0x8B, 0x78)
INK = RGBColor(0x22, 0x2B, 0x27)
INK_SOFT = RGBColor(0x3B, 0x4A, 0x42)
GREY = RGBColor(0x7A, 0x86, 0x80)

BODY_FONT = "Calibri"
HEAD_FONT = "Georgia"

TBLPR_ORDER = ["tblStyle", "tblpPr", "tblOverlap", "bidiVisual", "tblStyleRowBandSize",
               "tblStyleColBandSize", "tblW", "jc", "tblCellSpacing", "tblInd", "tblBorders",
               "shd", "tblLayout", "tblCellMar", "tblLook", "tblCaption", "tblDescription"]

PBRD_PRECEDERS = ("pStyle", "keepNext", "keepLines", "pageBreakBefore", "framePr",
                  "widowControl", "numPr", "suppressLineNumbers")


# ----------------------------------------------------------------- helpers OOXML
def _local(tag):
    return tag.split("}")[1] if "}" in tag else tag


def tblpr_insert(tbl, el):
    tblPr = tbl._tbl.tblPr
    name = _local(el.tag)
    idx = TBLPR_ORDER.index(name)
    pos = len(tblPr)
    for i, child in enumerate(list(tblPr)):
        cname = _local(child.tag)
        if cname in TBLPR_ORDER and TBLPR_ORDER.index(cname) > idx:
            pos = i
            break
    tblPr.insert(pos, el)


def set_tbl_borders(tbl, color="D6DED8", strong="1F4E3D"):
    tblBorders = OxmlElement("w:tblBorders")
    spec = {"top": (strong, 8), "left": ("nil", 0), "bottom": (strong, 8),
            "right": ("nil", 0), "insideH": (color, 4), "insideV": ("nil", 0)}
    for edge in ("top", "left", "bottom", "right", "insideH", "insideV"):
        col, sz = spec[edge]
        e = OxmlElement("w:" + edge)
        if col == "nil":
            e.set(qn("w:val"), "nil")
        else:
            e.set(qn("w:val"), "single")
            e.set(qn("w:sz"), str(sz))
            e.set(qn("w:space"), "0")
            e.set(qn("w:color"), col)
        tblBorders.append(e)
    tblpr_insert(tbl, tblBorders)


def set_tbl_cell_margins(tbl, top=70, left=120, bottom=70, right=120):
    mar = OxmlElement("w:tblCellMar")
    for edge, val in (("top", top), ("left", left), ("bottom", bottom), ("right", right)):
        e = OxmlElement("w:" + edge)
        e.set(qn("w:w"), str(val))
        e.set(qn("w:type"), "dxa")
        mar.append(e)
    tblpr_insert(tbl, mar)


def shade_cell(cell, hexfill):
    tcPr = cell._tc.get_or_add_tcPr()
    for old in tcPr.findall(qn("w:shd")):
        tcPr.remove(old)
    shd = OxmlElement("w:shd")
    shd.set(qn("w:val"), "clear")
    shd.set(qn("w:color"), "auto")
    shd.set(qn("w:fill"), hexfill)
    tcPr.append(shd)


def cell_single_border(cell, edge, color, sz=8):
    tcPr = cell._tc.get_or_add_tcPr()
    borders = tcPr.find(qn("w:tcBorders"))
    if borders is None:
        borders = OxmlElement("w:tcBorders")
        tcPr.append(borders)
    e = OxmlElement("w:" + edge)
    e.set(qn("w:val"), "single")
    e.set(qn("w:sz"), str(sz))
    e.set(qn("w:space"), "0")
    e.set(qn("w:color"), color)
    borders.append(e)


def repeat_header(row):
    trPr = row._tr.get_or_add_trPr()
    el = OxmlElement("w:tblHeader")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def no_split(row):
    trPr = row._tr.get_or_add_trPr()
    el = OxmlElement("w:cantSplit")
    el.set(qn("w:val"), "true")
    trPr.append(el)


def p_border(par, edge="bottom", color="1F4E3D", sz=8, space=4):
    pPr = par._p.get_or_add_pPr()
    pBdr = pPr.find(qn("w:pBdr"))
    if pBdr is None:
        pBdr = OxmlElement("w:pBdr")
        idx = 0
        for i, child in enumerate(list(pPr)):
            if _local(child.tag) in PBRD_PRECEDERS:
                idx = i + 1
        pPr.insert(idx, pBdr)
    e = OxmlElement("w:" + edge)
    e.set(qn("w:val"), "single")
    e.set(qn("w:sz"), str(sz))
    e.set(qn("w:space"), str(space))
    e.set(qn("w:color"), color)
    pBdr.append(e)


def add_field(par, instr, placeholder="1"):
    r1 = par.add_run()
    fc1 = OxmlElement("w:fldChar"); fc1.set(qn("w:fldCharType"), "begin"); r1._r.append(fc1)
    r2 = par.add_run()
    it = OxmlElement("w:instrText"); it.set(qn("xml:space"), "preserve"); it.text = instr
    r2._r.append(it)
    r3 = par.add_run()
    fc2 = OxmlElement("w:fldChar"); fc2.set(qn("w:fldCharType"), "separate"); r3._r.append(fc2)
    r4 = par.add_run(placeholder)
    r5 = par.add_run()
    fc3 = OxmlElement("w:fldChar"); fc3.set(qn("w:fldCharType"), "end"); r5._r.append(fc3)
    return [r1, r2, r3, r4, r5]


# ----------------------------------------------------------------- helpers de texto
def p(doc, text="", size=10.5, color=INK, bold=False, italic=False, align=None,
      before=0, after=7, spacing=1.2, indent=0.0, keep=False, font=BODY_FONT):
    par = doc.add_paragraph()
    pf = par.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after = Pt(after)
    pf.line_spacing = spacing
    if indent:
        pf.left_indent = Cm(indent)
    if align is not None:
        pf.alignment = align
    if keep:
        pf.keep_with_next = True
    if text:
        run = par.add_run(text)
        run.font.name = font
        run.font.size = Pt(size)
        run.font.bold = bold
        run.font.italic = italic
        run.font.color.rgb = color
    return par


def rich(doc, parts, size=10.5, align=WD_ALIGN_PARAGRAPH.JUSTIFY, before=0, after=7,
         spacing=1.2, indent=0.0):
    """parts: lista de (texto, dict de formatação)."""
    par = doc.add_paragraph()
    pf = par.paragraph_format
    pf.space_before = Pt(before)
    pf.space_after = Pt(after)
    pf.line_spacing = spacing
    if indent:
        pf.left_indent = Cm(indent)
    if align is not None:
        pf.alignment = align
    for text, fmt in parts:
        run = par.add_run(text)
        run.font.name = fmt.get("font", BODY_FONT)
        run.font.size = Pt(fmt.get("size", size))
        run.font.bold = fmt.get("bold", False)
        run.font.italic = fmt.get("italic", False)
        run.font.color.rgb = fmt.get("color", INK)
    return par


def bullets(doc, items, size=10.5):
    for item in items:
        par = doc.add_paragraph(style="List Bullet")
        pf = par.paragraph_format
        pf.space_before = Pt(1)
        pf.space_after = Pt(4)
        pf.line_spacing = 1.15
        pf.left_indent = Cm(0.7)
        pf.first_line_indent = Cm(-0.35)
        pf.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        if isinstance(item, tuple):
            head, rest = item
            r = par.add_run(head)
            r.font.name = BODY_FONT; r.font.size = Pt(size); r.font.bold = True; r.font.color.rgb = INK
            r2 = par.add_run(rest)
            r2.font.name = BODY_FONT; r2.font.size = Pt(size); r2.font.color.rgb = INK
        else:
            r = par.add_run(item)
            r.font.name = BODY_FONT; r.font.size = Pt(size); r.font.color.rgb = INK


def quote(doc, text, speaker=None):
    par = doc.add_paragraph()
    pf = par.paragraph_format
    pf.left_indent = Cm(0.9)
    pf.right_indent = Cm(0.4)
    pf.space_before = Pt(5)
    pf.space_after = Pt(9)
    pf.line_spacing = 1.15
    pf.alignment = WD_ALIGN_PARAGRAPH.LEFT
    if speaker:
        r = par.add_run(speaker + ": ")
        r.font.name = BODY_FONT; r.font.size = Pt(9.5); r.font.bold = True
        r.font.color.rgb = GREEN_MID
    r2 = par.add_run("\u201c" + text + "\u201d")
    r2.font.name = BODY_FONT; r2.font.size = Pt(9.5); r2.font.italic = True
    r2.font.color.rgb = INK_SOFT
    p_border(par, "left", "8FAF9B", 12, 8)
    return par


def h1(doc, number, text, page_break=False):
    par = doc.add_paragraph(style="Heading 1")
    pf = par.paragraph_format
    pf.space_before = Pt(20)
    pf.space_after = Pt(10)
    pf.keep_with_next = True
    if page_break:
        pf.page_break_before = True
    r = par.add_run(number + "  ")
    r.font.name = HEAD_FONT; r.font.size = Pt(15); r.font.bold = True
    r.font.color.rgb = RGBColor(0x8F, 0xAF, 0x9B)
    r2 = par.add_run(text)
    r2.font.name = HEAD_FONT; r2.font.size = Pt(15); r2.font.bold = True
    r2.font.color.rgb = GREEN_DARK
    p_border(par, "bottom", "C9D8CF", 8, 6)
    return par


def h2(doc, text, first=False):
    par = doc.add_paragraph(style="Heading 2")
    pf = par.paragraph_format
    pf.space_before = Pt(14 if not first else 8)
    pf.space_after = Pt(5)
    pf.keep_with_next = True
    r = par.add_run(text)
    r.font.name = HEAD_FONT; r.font.size = Pt(11.5); r.font.bold = True
    r.font.color.rgb = GREEN_MID
    return par


def table(doc, headers, rows, widths, header_fill="1F4E3D", font_size=9,
          band=True, first_col_bold=False):
    tbl = doc.add_table(rows=1, cols=len(headers))
    tbl.style = "Table Grid"
    tbl.alignment = WD_TABLE_ALIGNMENT.CENTER
    tbl.autofit = False
    set_tbl_borders(tbl)
    set_tbl_cell_margins(tbl)
    # grade de larguras
    for i, w in enumerate(widths):
        tbl.columns[i].width = Cm(w)
    hdr = tbl.rows[0]
    no_split(hdr)
    repeat_header(hdr)
    for i, text in enumerate(headers):
        cell = hdr.cells[i]
        cell.width = Cm(widths[i])
        shade_cell(cell, header_fill)
        par = cell.paragraphs[0]
        pf = par.paragraph_format
        pf.space_before = Pt(1); pf.space_after = Pt(1); pf.line_spacing = 1.0
        r = par.add_run(text)
        r.font.name = BODY_FONT; r.font.size = Pt(font_size); r.font.bold = True
        r.font.color.rgb = RGBColor(0xFF, 0xFF, 0xFF)
    for ri, row_vals in enumerate(rows):
        row = tbl.add_row()
        no_split(row)
        for ci, val in enumerate(row_vals):
            cell = row.cells[ci]
            cell.width = Cm(widths[ci])
            if band and ri % 2 == 1:
                shade_cell(cell, "F3F7F4")
            par = cell.paragraphs[0]
            pf = par.paragraph_format
            pf.space_before = Pt(1); pf.space_after = Pt(1); pf.line_spacing = 1.1
            r = par.add_run(val)
            r.font.name = BODY_FONT; r.font.size = Pt(font_size)
            r.font.color.rgb = INK
            if ci == 0 and first_col_bold:
                r.font.bold = True
                r.font.color.rgb = GREEN_DARK
    p(doc, "", after=4, size=4)
    return tbl


def callout(doc, title, lines, fill="EEF3EF"):
    tbl = doc.add_table(rows=1, cols=1)
    tbl.autofit = False
    tbl.columns[0].width = Cm(16.0)
    cell = tbl.rows[0].cells[0]
    cell.width = Cm(16.0)
    cell_single_border(cell, "left", "2F6B4F", 24)
    shade_cell(cell, fill)
    par = cell.paragraphs[0]
    par.paragraph_format.space_before = Pt(3)
    par.paragraph_format.space_after = Pt(3)
    if title:
        r = par.add_run(title)
        r.font.name = BODY_FONT; r.font.size = Pt(9.5); r.font.bold = True
        r.font.color.rgb = GREEN_DARK
    for ln in lines:
        par2 = cell.add_paragraph()
        pf = par2.paragraph_format
        pf.space_before = Pt(2); pf.space_after = Pt(3); pf.line_spacing = 1.12
        pf.left_indent = Cm(0.0)
        r = par2.add_run(ln)
        r.font.name = BODY_FONT; r.font.size = Pt(9.5)
        r.font.color.rgb = INK_SOFT
    p(doc, "", after=6, size=4)


def bullet_in_callout(doc, title, items):
    tbl = doc.add_table(rows=1, cols=1)
    tbl.autofit = False
    tbl.columns[0].width = Cm(16.0)
    cell = tbl.rows[0].cells[0]
    cell.width = Cm(16.0)
    cell_single_border(cell, "left", "2F6B4F", 24)
    shade_cell(cell, "EEF3EF")
    par = cell.paragraphs[0]
    par.paragraph_format.space_before = Pt(3)
    par.paragraph_format.space_after = Pt(4)
    r = par.add_run(title)
    r.font.name = BODY_FONT; r.font.size = Pt(9.5); r.font.bold = True
    r.font.color.rgb = GREEN_DARK
    for item in items:
        par2 = cell.add_paragraph()
        pf = par2.paragraph_format
        pf.space_before = Pt(1); pf.space_after = Pt(3); pf.line_spacing = 1.12
        pf.left_indent = Cm(0.45)
        pf.first_line_indent = Cm(-0.3)
        pf.alignment = WD_ALIGN_PARAGRAPH.JUSTIFY
        rb = par2.add_run("\u2022  ")
        rb.font.name = BODY_FONT; rb.font.size = Pt(9.5); rb.font.color.rgb = GREEN_MID
        r = par2.add_run(item)
        r.font.name = BODY_FONT; r.font.size = Pt(9.5)
        r.font.color.rgb = INK_SOFT
    p(doc, "", after=6, size=4)


# ----------------------------------------------------------------- documento
doc = Document()

# idioma padrão pt-BR
normal = doc.styles["Normal"]
normal.font.name = BODY_FONT
normal.font.size = Pt(10.5)
normal.font.color.rgb = INK
rpr = normal.element.get_or_add_rPr()
lang = OxmlElement("w:lang")
lang.set(qn("w:val"), "pt-BR"); lang.set(qn("w:eastAsia"), "pt-BR"); lang.set(qn("w:bidi"), "ar-SA")
rpr.append(lang)

sec = doc.sections[0]
sec.page_width = Cm(21.0)
sec.page_height = Cm(29.7)
sec.top_margin = Cm(2.2)
sec.bottom_margin = Cm(2.0)
sec.left_margin = Cm(2.5)
sec.right_margin = Cm(2.5)
sec.header_distance = Cm(1.1)
sec.footer_distance = Cm(1.0)
sec.different_first_page_header_footer = True

# cabeçalho
hp = sec.header.paragraphs[0]
hp.alignment = WD_ALIGN_PARAGRAPH.RIGHT
hp.paragraph_format.space_after = Pt(2)
hr = hp.add_run("ASSOCIAÇÃO JOSÉ LUTZENBERGER  \u00b7  REUNIÃO DE 9 DE NOVEMBRO DE 2026")
hr.font.name = BODY_FONT; hr.font.size = Pt(7.5); hr.font.color.rgb = GREY
hr.font.bold = True
p_border(hp, "bottom", "D6DED8", 4, 3)

# rodapé com numeração
fp = sec.footer.paragraphs[0]
fp.paragraph_format.space_before = Pt(2)
fp.paragraph_format.tab_stops.add_tab_stop(Cm(16.0), WD_TAB_ALIGNMENT.RIGHT)


def footer_run(text):
    r = fp.add_run(text)
    r.font.name = BODY_FONT; r.font.size = Pt(7.5); r.font.color.rgb = GREY
    return r


footer_run("Compilação temática da transcrição \u00b7 documento de uso interno")
footer_run("\tPágina ")
for r in add_field(fp, "PAGE"):
    r.font.name = BODY_FONT; r.font.size = Pt(7.5); r.font.color.rgb = GREY
footer_run(" de ")
for r in add_field(fp, "NUMPAGES"):
    r.font.name = BODY_FONT; r.font.size = Pt(7.5); r.font.color.rgb = GREY

# ---------------- CAPA ----------------
p(doc, "", after=30, size=6)
lab = p(doc, "MEMÓRIA DE REUNIÃO", size=9, color=GREEN_SOFT, bold=True,
        align=WD_ALIGN_PARAGRAPH.LEFT, after=14)
lab.runs[0].font.name = BODY_FONT

title = p(doc, "Assessoria à Associação\nJosé Lutzenberger", size=25, color=RGBColor(0x14, 0x36, 0x2A),
          bold=True, font=HEAD_FONT, after=8, spacing=1.08, align=WD_ALIGN_PARAGRAPH.LEFT)
title.paragraph_format.line_spacing = 1.05

rule = p(doc, "", after=10, before=2)
p_border(rule, "bottom", "1F4E3D", 12, 1)

p(doc, "Compilação temática da transcrição: o que foi discutido, as ideias levantadas "
       "e os encaminhamentos do encontro", size=12.5, color=GREEN_MID, italic=True,
  after=26, spacing=1.25, align=WD_ALIGN_PARAGRAPH.LEFT)

info = [
    ("Data", "9 de novembro de 2026"),
    ("Duração", "cerca de duas horas de gravação, em três blocos (39 min, 12 min e 1h08), com quedas de conexão no meio"),
    ("Formato", "reunião on-line, transcrita automaticamente pela extensão Tactiq"),
    ("Participantes", "Eduardo Baptista (convidado) e integrantes da Associação José Lutzenberger — "
                      "Edmilson (registrado também como \u201cedy\u201d) e Marcia Riva; há menção a Rose"),
    ("Eixo da conversa", "comercialização, identidade territorial, turismo rural, diversificação de renda "
                         "e sucessão na agricultura familiar"),
    ("Idioma e critério", "português do Brasil; trechos sem conteúdo em português (ruído da transcrição "
                          "automática) foram suprimidos"),
]
tbl = doc.add_table(rows=0, cols=2)
tbl.autofit = False
tbl.columns[0].width = Cm(3.9)
tbl.columns[1].width = Cm(12.1)
for k, v in info:
    row = tbl.add_row()
    c0, c1 = row.cells
    c0.width = Cm(3.9); c1.width = Cm(12.1)
    par = c0.paragraphs[0]
    par.paragraph_format.space_before = Pt(2); par.paragraph_format.space_after = Pt(2)
    r = par.add_run(k)
    r.font.name = BODY_FONT; r.font.size = Pt(9); r.font.bold = True; r.font.color.rgb = GREEN_SOFT
    par2 = c1.paragraphs[0]
    par2.paragraph_format.space_before = Pt(2); par2.paragraph_format.space_after = Pt(2)
    par2.paragraph_format.line_spacing = 1.1
    r2 = par2.add_run(v)
    r2.font.name = BODY_FONT; r2.font.size = Pt(9); r2.font.color.rgb = INK

p(doc, "", after=18, size=6)
band = doc.add_table(rows=1, cols=1)
band.autofit = False
band.columns[0].width = Cm(16.0)
bc = band.rows[0].cells[0]
bc.width = Cm(16.0)
shade_cell(bc, "1F4E3D")
bp = bc.paragraphs[0]
bp.paragraph_format.space_before = Pt(5); bp.paragraph_format.space_after = Pt(5)
bp.paragraph_format.alignment = WD_ALIGN_PARAGRAPH.CENTER
br = bp.add_run("Transcrição organizada por temas  \u00b7  conteúdo integralmente em português do Brasil  \u00b7  uso interno da associação")
br.font.name = BODY_FONT; br.font.size = Pt(8); br.font.bold = True
br.font.color.rgb = RGBColor(0xE8, 0xEF, 0xEA)

doc.add_page_break()

# ---------------- 1. COMO LER ----------------
h1(doc, "1", "Como ler este documento")

p(doc, "Esta compilação nasceu de uma necessidade prática: a reunião de 9 de novembro de 2026 rendeu mais de uma "
       "hora de conversa densa, com dezenas de ideias, exemplos e recomendações, mas o registro disponível era a "
       "transcrição automática da plataforma — longa, repetitiva e cheia de falhas. O objetivo aqui é devolver ao "
       "grupo um texto que se lê em vinte minutos e que está organizado o suficiente para que qualquer pessoa, "
       "inclusive quem não participou do encontro, entenda os temas tratados, as propostas e o que ficou combinado.",
  after=8)

p(doc, "Não se trata de uma ata formal. A reunião foi uma conversa de assessoria, sem pauta rígida e sem "
       "deliberações. Por isso, o que está registrado são temas, argumentos, exemplos e sugestões — e não decisões "
       "fechadas. Onde houve recomendação explícita, ela aparece identificada como tal.", after=10)

bullet_in_callout(doc, "Critérios de edição aplicados", [
    "Somente conteúdo em português do Brasil. Ruídos de reconhecimento de voz, saudações isoladas, frases "
    "transcritas em outro idioma e mensagens automáticas da ferramenta entraram no corte — é por isso que a "
    "conversa, aqui, começa direto no assunto.",
    "Ordem temática em vez de ordem cronológica. A seção 7 (Linha do tempo) devolve a sequência dos assuntos com "
    "os horários da gravação, para quem precisar conferir.",
    "Edição leve das falas: pontuação, concordância e repetições foram ajustadas para a leitura, sem mudança de "
    "sentido. As citações aparecem destacadas e atribuídas a quem falou.",
    "Nomes de pessoas e instituições foram normalizados para a grafia usual quando a transcrição automática os "
    "deformou. A seção 8 explica quem é quem e o que é cada instituição.",
    "Horários são aproximados e recomeçam em 00:00 a cada bloco de gravação; a seção 7 indica o bloco de cada "
    "trecho. A transcrição registra o mesmo participante ora como \u201cEdmilson\u201d, ora como \u201cedy\u201d; "
    "trata-se, muito provavelmente, da mesma pessoa.",
    "Em caso de divergência entre este texto e a gravação, vale a gravação.",
])

rich(doc, [
    ("Roteiro de leitura. ", {"bold": True, "color": GREEN_DARK}),
    ("Quem tem pressa deve começar pelo resumo executivo (seção 2) e ir direto aos encaminhamentos (seção 6). "
     "Quem vai trabalhar nas ideias encontra na seção 5 a lista organizada por horizonte de tempo. Quem precisa "
     "entender o que foi dito em cada momento da conversa pode usar a linha do tempo (seção 7).", {}),
], after=6)

# ---------------- 2. RESUMO EXECUTIVO ----------------
h1(doc, "2", "Resumo executivo")

p(doc, "A reunião foi uma assessoria: Eduardo Baptista, convidado com longa trajetória em agroecologia, feiras e "
       "cooperação com fundações doadoras, ajudou a associação a olhar para si mesma e a organizar o que fazer com "
       "o que já tem. O diagnóstico de partida é conhecido do grupo: domina-se a produção e a feira, mas há meses "
       "de renda baixa (de dezembro a fevereiro), desgaste das pessoas que sustentam as atividades, dificuldade de "
       "atrair os mais jovens, dependência de uma única forma de vender e escassez de recursos para estruturar "
       "qualquer coisa nova.", after=8)

p(doc, "Do lado positivo, o grupo já testou parte do que se discute. Houve festas comunitárias e comemorações do "
       "aniversário do assentamento, domingos de visitação para os consumidores da feira e visitas de escolas — "
       "atividades que a pandemia interrompeu e que podem ser retomadas e ampliadas. A avaliação de Edmilson é que "
       "\u201cos parceiros urbanos têm uma carência dessa vivência\u201d: existe demanda, faltam organização e "
       "estrutura para receber bem.", after=8)

p(doc, "Eduardo propôs um caminho em três movimentos. Primeiro, entender que comercialização tem três canais — "
       "venda direta (feira, lojinha, visitação), mercados institucionais (programas de aquisição de alimentos) e "
       "parcerias (restaurantes, redes, eventos) — e que a associação hoje está concentrada no primeiro. Segundo, "
       "transformar a identidade territorial em marca: o assentamento tem mais de trinta anos, foi o primeiro da "
       "reforma agrária no Rio Grande do Sul a produzir orgânico e tem história de sobra, mas essa história não "
       "está visível nem é usada como ativo. Terceiro, escolher no máximo três frentes e separar o que é curto, "
       "médio e longo prazo usando o exercício \u201cvaca, bezerro e pasto\u201d.", after=8)

p(doc, "Na parte prática, a conversa produziu um conjunto de ideias que se encaixam bem: receber visitantes com "
       "visita guiada e almoço preparado por chefs locais; colher-pague em pomar de frutas nativas; mini "
       "agroindústria de desidratados e conservas para atravessar o verão; reaproveitar o contêiner obtido junto à "
       "BrazilFoundation como espaço de acolhida; festa e roda de conversa com os jovens no fim do ano; e resgate "
       "da memória do assentamento com estudantes, material institucional e página própria.", after=8)

p(doc, "Os gargalos apontados são igualmente claros: falta de braços (as mesmas pessoas fazem feira, produção e "
       "recepção), resistência interna a mudar o que já funciona, ausência de estrutura e de caixa — \u201ceu hoje "
       "não tenho cinco mil reais para botar um telhadinho\u201d — e pouca articulação com universidades, Sebrae, "
       "fundações e editais. Eduardo ofereceu pontes concretas (BrazilFoundation, Fundação Gaia, Domingos Armani, "
       "universidades) e recomendou que o grupo não tente abraçar tudo.", after=10)

callout(doc, "Em uma frase", [
    "A associação já tem produto, história e demanda; o que falta é escolher poucas frentes, transformar "
    "identidade em marca e montar as parcerias que financiem a estrutura.",
])

# ---------------- 3. PARTICIPANTES ----------------
h1(doc, "3", "Participantes, território e ponto de partida")

h2(doc, "Quem estava na conversa", first=True)
table(doc,
      ["Nome", "Como aparece e qual foi o papel"],
      [["Eduardo Baptista", "Convidado. Conduz a conversa, apresenta os métodos (canais de comercialização, "
                            "identidade territorial, vaca/bezerro/pasto) e oferece contatos e pontes institucionais."],
       ["Edmilson", "Integrante da associação. Apresenta a realidade do grupo, as ideias e as dificuldades; fala "
                    "também nos blocos em que a transcrição usa o rótulo \u201cedy\u201d. Cita a produção de "
                    "cogumelos e o Sítio do Tigre."],
       ["Marcia Riva", "Participante registrada no primeiro bloco da gravação; não tem falas de conteúdo na "
                       "transcrição."],
       ["Rose", "Integrante do grupo citada por Edmilson e mencionada como presente no encontro, embora não conste "
                "da lista de participantes da gravação; já vinha discutindo o processamento e a durabilidade dos "
                "produtos, tema retomado no fim da conversa."]],
      [3.6, 12.4], first_col_bold=True)

h2(doc, "Quem é o convidado")
p(doc, "Eduardo Baptista se apresentou como alguém que passou a vida entre a universidade e o mundo rural. Contou "
       "que acompanha atividades agrícolas há cerca de quarenta anos, desde os anos 1990: participou da criação da "
       "Rede Ecovida, acompanhou de perto o Instituto Centro Ecológico, na região de Torres, e projetos apoiados por "
       "fundações doadoras — que, como fez questão de lembrar, nunca são de graça e sempre têm contrapartidas. Deu "
       "aulas de administração e de marketing, aposentou-se e seguiu circulando por experiências de agroecologia e "
       "turismo rural no Sul, no Nordeste e no exterior. Propôs, desde o início, uma conversa sem formalidade — mais "
       "roda de conversa do que sala de aula —, e ela foi conduzida assim.", after=8)

h2(doc, "O território")
p(doc, "A conversa se refere à Associação José Lutzenberger, ligada a um assentamento da reforma agrária em "
       "Alvorada, na região metropolitana de Porto Alegre, criado no final de 1991 — portanto, com cerca de trinta "
       "e cinco anos. As famílias vieram da região norte do estado, onde trabalhavam com soja, milho e feijão, e "
       "passaram a produzir arroz com agrotóxico. O uso intensivo de veneno, o adoecimento das pessoas e a "
       "mortandade de animais levaram o grupo a buscar outra forma de produzir. A virada veio com a assessoria "
       "técnica associada ao ambientalista José Lutzenberger, cujo nome a associação adotou.", after=8)

p(doc, "Hoje a associação tem feira semanal aos sábados, produção de hortaliças e cogumelos, um pomar com cerca "
       "de cem pés de frutíferas nativas — entre trinta e quarenta variedades, incluindo bergamota, maçã, guabiroba "
       "e araçá — e um espaço de eventos, o Sítio do Tigre, com agenda de casamentos e aniversários até janeiro. "
       "Segundo Edmilson, foi o primeiro assentamento da reforma agrária do Rio Grande do Sul a produzir orgânico, "
       "experiência que depois se espalhou para outros assentamentos e regiões.", after=8)

p(doc, "O ponto de partida da conversa é essa combinação de força e travamento: identidade e demanda existem, mas "
       "não se transformam em trabalho e renda de forma estável.", after=6)

quote(doc, "A gente tem um acúmulo, uma identidade que é muito importante. Mas, para cada norte, a gente esbarra "
           "em como instrumentalizar isso: tirar de uma demanda dos nossos parceiros urbanos e transformar na "
           "riqueza que a gente tem aqui, numa atração, num espaço de geração de trabalho e renda.",
      "Edmilson")

# ---------------- 4. TEMAS ----------------
h1(doc, "4", "Temas tratados", page_break=True)
p(doc, "Esta seção reconstrói a conversa tema por tema, na ordem em que os assuntos ganharam corpo no encontro. "
       "Cada bloco reúne o que foi dito, os exemplos usados e, quando houve, as recomendações.", after=4)

h2(doc, "4.1  As feiras: onde a humanidade aprendeu a trocar", first=True)
p(doc, "Eduardo abriu a parte técnica da conversa pela feira, que chamou de \u201co principal evento de "
       "comercialização para o pequeno produtor\u201d. Antes da técnica, fez um desvio pela história: as feiras, na "
       "avaliação dele, estão entre as invenções que mais fizeram a humanidade avançar, porque eram o lugar onde "
       "produtos e ideias circulavam; no Brasil, os tropeiros cumpriram parte desse papel, levando e trazendo "
       "mercadorias entre regiões.", after=7)

p(doc, "Daí a recomendação: quem faz feira precisa lembrar — e lembrar os outros — que a feira é lugar de "
       "progresso e de troca de conhecimento, não apenas de venda. O risco é o oposto: a feira virar um negócio "
       "repetitivo e o sentido original se perder. \u201cSe está tudo muito igual, eu tenho que dar uma parada e "
       "pensar o que posso fazer de diferente.\u201d", after=7)

p(doc, "Para ilustrar, contou o caso de uma feira no Paraná: uma senhora vendia bolos havia anos e um dia anunciou "
       "que ia se aposentar. A compradora que, havia mais de dez anos, levava os bolos toda semana entrou em "
       "desespero — a família inteira acreditava que era ela quem fazia os bolos, e ela não queria que ninguém "
       "soubesse. \u201cEu nunca tinha pensado que um bolo podia ser tão importante na vida de uma pessoa.\u201d A "
       "leitura de Eduardo: produtos carregam afeto, memória e identidade, e a feira é sobretudo um lugar de "
       "relação — o que também é um ativo.", after=7)

h2(doc, "4.2  Os três canais de comercialização")
p(doc, "Na linguagem do pessoal de administração e de marketing, existem três canais. O primeiro é a venda "
       "direta: feira, lojinha, pontos de venda coletivos, visitação e turismo rural ligado à produção. O segundo "
       "são os mercados institucionais: programas governamentais de compra de alimentos para escolas e hospitais, "
       "no âmbito federal, estadual ou municipal. O terceiro são as parcerias: redes de supermercado, mercadinhos, "
       "eventos e festivais gastronômicos. A associação está bem posicionada no primeiro canal e pouco presente nos "
       "outros dois.", after=7)

p(doc, "Sobre os programas institucionais, Eduardo citou o conjunto sem fixar a sigla — \u201cestou trocando as "
       "siglas\u201d —, referindo-se aos programas de aquisição de alimentos que incluem o Programa de Aquisição de "
       "Alimentos e o Programa Nacional de Alimentação Escolar, e lembrou que estados e municípios mantêm programas "
       "próprios. Vale conferir os editais em vigor no município e no estado.", after=7)

p(doc, "Dentro da venda direta, mencionou os pontos de venda criados por associações e cooperativas: no Rio "
       "Grande do Sul, as lojinhas do Centro Ecológico entre Porto Alegre e a região de Torres; no Paraná, duas "
       "lojas à beira da rodovia Curitiba–Foz do Iguaçu, inclusive criadas por comunidades indígenas, aproveitando "
       "o fluxo de turistas.", after=7)

h2(doc, "4.3  Identidade territorial: a \u201ccara\u201d do lugar")
p(doc, "O tema atravessou toda a reunião. Para Eduardo, identidade territorial é o que faz alguém escolher o seu "
       "produto entre dez iguais: \u201ca cara, a marca de um lugar\u201d. Citou a Itália e a França, onde a ideia "
       "de território é muito marcada, e lembrou que pacote de café, azeite e vinho vendem contando quem produz e "
       "onde — o exemplo bem-humorado do sal do Himalaia serviu justamente para mostrar que origem, verdadeira ou "
       "não, é argumento de venda.", after=7)

p(doc, "Aplicado ao assentamento, o diagnóstico é agridoce. Houve tempo em que havia festas, comemorações do "
       "aniversário do assentamento e uma identidade forte; isso \u201cfoi se diluindo\u201d com o passar do tempo. "
       "A associação tem nome, história e um logo desenhado por Maria, filha de uma das famílias, mas não tem "
       "página, material institucional nem apresentação para fora — nas palavras de Edmilson, \u201cestá muito "
       "internamente ainda\u201d.", after=7)

quote(doc, "O que a gente tem é essa identidade rural e agroecológica. Isso é uma riqueza gigante, e nós mesmos "
           "não temos muita noção do quanto isso é grande, do quanto isso é importante.", "Edmilson")

p(doc, "A recomendação é dar ao território os atributos de uma marca: data de nascimento, aniversário comemorado, "
       "logo, página, material de divulgação. Sem marca, o interesse das pessoas se dispersa, porque há oferta "
       "demais competindo por atenção.", after=7)

quote(doc, "Conhecer é muito importante. Agora, para conhecer, vocês têm que ter uma marca para colar nessas "
           "pessoas; senão fica disperso: a pessoa acha bonito, não se liga depois porque tem muitas outras coisas.",
      "Eduardo Baptista")

h2(doc, "4.4  O que o grupo já viveu: festas, visitas e escolas")
p(doc, "Antes de falar do futuro, Edmilson fez o inventário do que a associação já teve. Houve um tempo em que o "
       "assentamento era muito mais organizado como território: comemorava-se o aniversário do assentamento, as "
       "festas da comunidade eram fortes e havia uma identidade coletiva que se reconhecia de fora. Com o passar do "
       "tempo, tudo isso \u201cfoi se diluindo\u201d.", after=7)

p(doc, "As visitas também já foram rotina. O grupo organizava domingos de visitação para os próprios consumidores "
       "da feira: divulgava, formava lista de interessados, recebia as famílias, mostrava as hortas, fazia almoço e "
       "devolvia todo mundo para casa. Algumas visitas foram de famílias inteiras, outras de grupos organizados. "
       "\u201cTivemos resultados fantásticos na época em que a gente fez\u201d — e a avaliação é que valeria retomar "
       "e explorar mais.", after=7)

p(doc, "A terceira frente que existiu foram as escolas: grupos escolares, inclusive de Guaíba, vinham conhecer a "
       "produção, com convivência de plantio e contato das crianças com a terra. A pandemia funcionou como um corte "
       "nesse fluxo; algumas escolas municipais voltaram a visitar o grupo recentemente.", after=7)

p(doc, "O que falta, na leitura de Edmilson, não é público — é organização para receber bem e continuidade para "
       "sustentar a agenda.", after=6)

quote(doc, "O pessoal urbano, eu vejo que os nossos parceiros urbanos têm uma carência dessa vivência. A gente "
           "poderia explorar mais.", "Edmilson")

h2(doc, "4.5  Quem faz: disponibilidade, sobrecarga e renovação")
p(doc, "Eduardo considerou esse o \u201cgrande desafio\u201d de qualquer iniciativa de acolhimento: a "
       "disponibilidade das pessoas. A primeira visita é divertida; a segunda chega depois de uma semana inteira de "
       "feira, e alguém precisa receber gente no fim de semana. Sem pausa, o cansaço vira explosão. Em qualquer "
       "associação, \u201cnem todo mundo tem o mesmo ritmo\u201d.", after=7)

p(doc, "Por isso, a primeira providência prática que sugeriu é uma conversa sincera: cada família diz até onde "
       "quer ir — \u201cdo jeito que está, para mim está bom\u201d é uma resposta legítima — e o que está disposta a "
       "fazer. Sem esse mapa de interesses e disponibilidades, qualquer plano de turismo ou de agroindústria nasce "
       "torto. E o coletivo não precisa esperar unanimidade: se três famílias querem uma frente, é com essas três "
       "que se avança.", after=7)

p(doc, "Dois exemplos de fora mostraram onde a renovação deu certo. Em Santa Catarina, o programa Acolhida na "
       "Colônia transformou secadores de fumo em pequenas pousadas. Os casos que prosperaram foram os que tinham "
       "filhos adolescentes e jovens envolvidos — mais as filhas do que os filhos, segundo Eduardo —, porque os "
       "pais já estavam sobrecarregados. O projeto cresceu muito (alguns viraram pousadas com lago e quiosque), e "
       "ele não sabe como está hoje. No interior de Pernambuco, perto da divisa com o Ceará, aconteceu algo "
       "parecido: os jovens assumiram as atividades, mas o crescimento rápido gerou desconforto entre os pais, "
       "incomodados com o protagonismo dos filhos.", after=7)

h2(doc, "4.6  Acolher visitantes: turismo rural, eventos e estrutura")
p(doc, "A ideia que mais mobilizou a conversa foi trazer gente para conhecer o lugar. Eduardo defendeu que o "
       "essencial é fazer as pessoas pisarem na propriedade — \u201cconhecer o lugar, conhecer as pessoas\u201d. "
       "Foi assim na experiência do rio Tapajós, quando um grupo de cerca de dez pessoas passou uma semana em uma "
       "associação, dormindo em condições precárias, tomando banho de rio e comendo comida simples; a visita virou "
       "uma parceria duradoura e deu origem ao Instituto Saúde e Alegria.", after=7)

p(doc, "No caso da associação, a proposta concreta que apareceu foi combinar visita guiada com almoço preparado "
       "por chefs de cozinha locais, no Sítio do Tigre, aproveitando a relação criada durante a campanha de ajuda "
       "na enchente, quando restaurantes e chefs de São Paulo se mobilizaram pelo grupo. A visita seria paga e "
       "geraria receita e divulgação.", after=7)

p(doc, "Outras possibilidades levantadas: colher-pague no pomar, com cestas pequena, média e grande (modelo que "
       "Eduardo viu com manga e amora no interior do Rio de Janeiro); colônia de férias em janeiro para famílias de "
       "classe média, inspirada na experiência pernambucana; e eventos em geral — casamentos, aniversários, "
       "festivais gastronômicos. Sobre eventos, o recado foi exigente: não basta \u201cdizer aparece em casa\u201d; "
       "é preciso divulgação, organização e objetivo definido — \u201ceu quero que cinco pessoas saiam desse evento "
       "com compromisso firmado\u201d.", after=7)

p(doc, "A estrutura é o limitador. A associação está obtendo contêineres em projeto com a BrazilFoundation, "
       "inicialmente para guardar bens durante as enchentes; a ideia é que um deles possa virar espaço de acolhida, "
       "\u201cao exemplo da Acolhida na Colônia\u201d, mas falta o telhado — cerca de cinco mil reais — e o caixa "
       "não existe: \u201ceu tenho que garantir a produção dos meus cogumelos\u201d.", after=7)

h2(doc, "4.7  Sazonalidade, perecibilidade e agregação de valor")
p(doc, "De dezembro a fevereiro quase não há hortaliça: a renda cai e o grupo precisa de outra atividade. Eduardo "
       "comparou com o defeso da pesca no Ceará — período em que não se pode pescar e as famílias precisam de "
       "alternativas — e reforçou que a resposta não é improvisar no verão, mas preparar a atividade com "
       "antecedência.", after=7)

p(doc, "Duas linhas de diversificação se destacaram. A primeira é o processamento mínimo: desidratar frutas, "
       "verduras (beterraba, cenoura) e cogumelos, o que permite guardar por mais de seis meses e vender no período "
       "de escassez. A segunda é a agroindústria leve: conservas, geleias e produtos diferenciados — com o alerta "
       "de não fazer o óbvio. Em vez de geleia de morango, que todo mundo faz, testar sabores que ninguém tem, como "
       "a mostarda de fruta do norte da Itália, feita com frutas em calda picante e canela.", after=7)

p(doc, "O pomar entra nessa conta. Edmilson tem cerca de cem pés de frutíferas nativas plantados, com trinta a "
       "quarenta variedades — bergamota, maçã, guabiroba, araçá —, que devem começar a produzir em dois a quatro "
       "anos. A ideia é soltar galinhas sob as fruteiras para fazer o manejo e, depois, receber clientes para colher "
       "e conhecer o pomar. Eduardo aprovou o desenho e lembrou que o modelo de cestas, com preço por tamanho, já "
       "funciona em vários lugares.", after=7)

p(doc, "O argumento que amarra tudo: \u201ca exceção dos meus risotos, o restante todo mundo produz algo — e em "
       "três dias já está estragado\u201d. Dar durabilidade ao que já se produz é, talvez, a forma mais direta de "
       "aumentar renda sem aumentar área.", after=7)

h2(doc, "4.8  Mudança de cultura: o que já deu certo como ponto de partida")
p(doc, "Edmilson foi direto: o grupo domina a produção e a feira \u201ccom excelência\u201d, mas mudar gera "
       "resistência — \u201cporque tu acha que não vai dar certo\u201d. Receber alguém na propriedade exige "
       "convencimento, aprendizado e capricho, porque a experiência tem de dar certo; não é simplesmente mandar "
       "vir.", after=7)

p(doc, "A história do assentamento é o melhor argumento de que essa mudança é possível. A saída do pacote "
       "químico — arroz com veneno, pragas e animais mortos — para a agroecologia exigiu assistência técnica, "
       "debate e convencimento, num tempo em que \u201cmudar de cultura não é fácil: pode estar ruim para ti, mas o "
       "que é desconhecido parece pior\u201d. O resultado foi o primeiro assentamento da reforma agrária do Rio "
       "Grande do Sul a produzir orgânico, experiência que se espalhou para outros assentamentos e regiões.", after=7)

p(doc, "O desafio de agora é parecido, mas em outro terreno: a pressão do agronegócio, a necessidade de inovação "
       "tecnológica e o risco de a pequena propriedade perder competitividade. Edmilson resumiu os dois obstáculos "
       "de acesso ao que já existe: falta de conhecimento para buscar e falta de dinheiro para implantar.", after=7)

h2(doc, "4.9  O exercício \u201cvaca, bezerro e pasto\u201d")
p(doc, "Da aula de marketing que Eduardo dava para turmas de administração veio o método que ele considera mais "
       "útil para organizar a cabeça: pensar a propriedade como uma fazendinha com três produtos. A vaca leiteira é "
       "o produto do presente, que já dá renda e não precisa de ajuda. O bezerro é o produto do futuro próximo, que "
       "está nascendo e vai precisar de cuidado. O pasto é o longo prazo, onde as coisas são preparadas para os bois "
       "e vacas \u201cnamorarem\u201d.", after=7)

p(doc, "Aplicado à associação, o mapa ficou assim: a vaca é a horta e a feira; o bezerro é o pomar, a colheita "
       "paga e a visitação; o pasto é a mini agroindústria de desidratados e conservas, que exige equipamentos, "
       "orçamento e tempo. O exercício pode ser feito individualmente, por família, e em grupo, pela associação — e "
       "serve para saber onde pedir ajuda: a vaca não precisa de nada; o bezerro talvez precise de informação e "
       "conexão; o pasto vai precisar de projeto e financiamento.", after=7)

quote(doc, "Você tem sempre a consciência desse extremo: o curto, o médio e o longo prazo. A vaquinha é produtiva, "
           "o bezerro está nascendo e o pasto é o que eu estou mantendo para eles namorarem.",
      "Eduardo Baptista")

h2(doc, "4.10  Foco: escolher pouco e registrar o caminho")
p(doc, "\u201cQuando a gente tem muitas alternativas, a gente fica perdido.\u201d O conselho repetido em vários "
       "momentos da conversa: definir uma, duas, no máximo três frentes e batalhar por elas. A associação tem "
       "oferta demais — turismo, frutas, desidratados, geleias, memória, eventos — e o excesso de possibilidades, "
       "sem foco, produz paralisia.", after=7)

p(doc, "Duas ferramentas foram sugeridas para sustentar o foco. A primeira é o mapa de interesses por família, "
       "para separar o que é convergente do que é específico de cada um. A segunda é o caderno de lições: "
       "registrar, depois de cada visita ou evento, o que estava previsto, o que aconteceu e o que não deu certo, "
       "para não repetir erros. Eduardo lembrou que hoje há escritórios que elaboram projetos e vivem de editais, "
       "mas nada substitui o registro de quem fez.", after=7)

p(doc, "Também foi apontado um problema de governança: o projeto de turismo rural está concentrado no Sítio do "
       "Tigre e ainda não é uma ação do coletivo da associação. Sem definir quem abraça cada frente, a ideia não "
       "anda.", after=7)

quote(doc, "Uma associação nunca é todo mundo igual. Se ficar esperando que todo mundo tenha o mesmo interesse "
           "para fazer alguma coisa, não vai fazer.", "Eduardo Baptista")

h2(doc, "4.11  Parcerias, financiamento e captação de recursos")
p(doc, "Eduardo foi enfático: existe muito mais dinheiro disponível do que o grupo imagina, mas é preciso saber "
       "onde procurar e como se apresentar. Ele organizou as portas em quatro grupos.", after=6)

bullets(doc, [
    ("Universidades. ", "Procurar os cursos de turismo, gastronomia, nutrição e engenharia de alimentos da região, "
     "falar com centros acadêmicos e professores: eles \u201cvivem caçando experiência para estudar\u201d e podem "
     "render estágios, trabalhos de conclusão e assessoria. O exemplo citado é o CEPAGRO, com sede dentro da UFSC, "
     "que liderou a criação da Rede Ecovida. Para a memória do assentamento, a sugestão foi anunciar tema para "
     "estudantes de história em conclusão de curso."),
    ("Poder público. ", "Programas estaduais e federais de apoio ao turismo, o Ministério do Turismo, o Sebrae — "
     "que recebe recursos públicos e tem obrigação de atender, bastando procurar o escritório regional — e emendas "
     "parlamentares, com a ressalva de que exigem organização e projeto."),
    ("Fundações e organismos de cooperação. ", "A BrazilFoundation, que já apoia o grupo e tem coordenadores "
     "técnicos; o Fundo Casa; e uma rede de cerca de vinte organizações semelhantes, algumas voltadas a públicos "
     "específicos, como povos indígenas e o Vale do Jequitinhonha. As embaixadas — Japão, Inglaterra, Canadá, "
     "Alemanha, Suíça — mantêm fundos para pequenas organizações."),
    ("Pessoas. ", "Referências que podem abrir portas, como Domingos Armani, de Porto Alegre, com quem Edmilson já "
     "iniciou contato; e a fundação ligada à família de José Lutzenberger, a Fundação Gaia, que fica em município "
     "vizinho, tem sítio aberto a visitações e possivelmente guarda registros históricos que a associação perdeu nas "
     "enchentes."),
])

p(doc, "Além disso, mencionou escritórios que elaboram projetos em troca de um percentual do valor aprovado — "
       "solução válida, mas que consome parte do recurso. E ofereceu-se para fazer pontes: falar com a "
       "BrazilFoundation sobre a possibilidade de a associação contar com o apoio de alguém com trânsito no mundo "
       "acadêmico e institucional, e apresentar o grupo a Domingos Armani ou a pessoas de sua equipe.", after=7)

h2(doc, "4.12  Memória, comunicação e a marca que falta")
p(doc, "A associação tem mais de trinta anos de história e perdeu parte dela nas enchentes — não só fotografias, "
       "mas documentos. \u201cÉ uma pena a gente não ter feito antes.\u201d Duas frentes foram sugeridas: resgatar a "
       "memória com estudantes (história, estágios, trabalhos de conclusão) e transformar o acervo em comunicação — "
       "material institucional, página, apresentação para parceiros e financiadores.", after=7)

p(doc, "Nesse ponto, pessoas concretas foram citadas: Maria, que fez o logo do grupo e estuda em São Paulo; "
       "Marines e Isabela, de quatorze anos, que gosta das frutas; Luiz Felipe e Gabriel, ligados à informática. A "
       "recomendação é aproveitar quem já está fora, inclusive quem trabalha em ambiente urbano, porque hoje muita "
       "coisa se faz remotamente.", after=7)

h2(doc, "4.13  Juventude: o teste de qualquer plano")
p(doc, "Eduardo voltou ao tema várias vezes: as experiências de acolhimento que deram certo foram as que "
       "envolveram jovens. E propôs um gesto simples e concreto para o fim do ano: uma festa ou roda de conversa só "
       "com os jovens da associação, aproveitando o Natal, quando os filhos voltam para a casa dos pais. A ideia é "
       "escutar o que eles querem fazer e deixar que puxem alguma frente.", after=7)

p(doc, "O argumento de fundo não é apenas sucessão: é qualidade de vida. \u201cTer uma vida mais saudável do que "
       "ficar em grandes cidades, duas horas dentro do ônibus para ir e duas para voltar, aquela perda de energia e "
       "de vida.\u201d", after=7)

# ---------------- 5. IDEIAS ----------------
h1(doc, "5", "Ideias e oportunidades levantadas", page_break=True)
p(doc, "A tabela reúne as ideias que apareceram na conversa, com uma descrição do que seria cada uma e o "
       "horizonte de tempo sugerido. Nenhuma delas foi decidida: a lista serve para a associação escolher as suas "
       "prioridades.", after=6)

table(doc,
      ["Ideia", "Como funcionaria", "Horizonte"],
      [["Visita guiada com almoço de chefs",
        "Receber chefs e convidados no Sítio do Tigre: visita guiada, almoço preparado pelos chefs e compra de "
        "produtos. Aproveita a relação criada na campanha da enchente.", "Curto/médio"],
       ["Colher-pague no pomar",
        "O visitante colhe frutas nativas e paga pela cesta (pequena, média ou grande). Modelo já testado com manga "
        "e amora no interior do Rio de Janeiro.", "Médio (2 a 4 anos, quando o pomar produzir)"],
       ["Mini agroindústria de desidratados",
        "Desidratar beterraba, cenoura, frutas e cogumelos: dura mais de seis meses e permite vender no verão. "
        "Exige estrutura e orçamento.", "Longo"],
       ["Conservas e geleias diferenciadas",
        "Sair do óbvio — nada de geleia de morango — e testar sabores e combinações, como a mostarda de fruta do "
        "norte da Itália.", "Longo"],
       ["Contêiner como espaço de acolhida",
        "Os contêineres do projeto com a BrazilFoundation servem para guardar bens durante a enchente; um deles "
        "poderia virar espaço de recepção. Falta o telhado (cerca de R$ 5 mil).", "Curto/médio"],
       ["Evento com chefs e restaurantes locais",
        "Trazer chefs da região para um evento no sítio; gera receita, divulgação e aproximação com o setor "
        "gastronômico.", "Curto"],
       ["Festa e roda de conversa com os jovens",
        "No fim do ano, aproveitando o Natal, reunir os jovens da associação e escutar o que eles querem fazer.",
        "Curto (dezembro)"],
       ["Resgate da memória do assentamento",
        "Parceria com estudantes de história (trabalho de conclusão e estágio) para sistematizar a história; parte "
        "das fotos e dos documentos se perdeu nas enchentes.", "Médio"],
       ["Material institucional e página",
        "Consolidar a identidade — logo (já desenhado por Maria), histórico, produtos e contatos — em material de "
        "apresentação e página própria.", "Curto/médio"],
       ["Visita técnica à Acolhida na Colônia",
        "Conhecer de perto a experiência catarinense que transformou secadores de fumo em pousadas.", "Curto"],
       ["Caderno de lições das visitas",
        "Registrar, após cada visita ou evento, o que estava previsto, o que aconteceu e o que deu certo, para não "
        "repetir erros.", "Contínuo"],
       ["Orçamento de estrutura e equipamentos",
        "Levantar custos da agroindústria e do espaço de acolhida antes de buscar financiamento.", "Curto"],
       ["Ampliação dos canais de venda",
        "Entrar em mercados institucionais (escolas e hospitais) e firmar parcerias com restaurantes, mercadinhos e "
        "redes.", "Médio"],
       ["Lanches e produtos práticos",
        "Desidratar cogumelos e criar lanches proteicos para público urbano, aproveitando a oferta que hoje se "
        "perde em três dias.", "Longo"]],
      [4.6, 8.6, 2.8], font_size=8.5, first_col_bold=True)

# ---------------- 6. ENCAMINHAMENTOS ----------------
h1(doc, "6", "Encaminhamentos e próximos passos")
p(doc, "As linhas abaixo reúnem o que foi sugerido durante a conversa. Não houve deliberação formal: responsáveis "
       "e prazos são indicações para discussão interna na associação, não compromissos assumidos no encontro.",
  after=6)

table(doc,
      ["Ação", "Detalhe", "Responsável sugerido", "Horizonte"],
      [["Definir no máximo três prioridades",
        "Escolher entre as frentes levantadas e concentrar esforço nelas; o conselho foi repetido várias vezes.",
        "Associação", "Imediato"],
       ["Mapear interesse e disponibilidade de cada família",
        "Conversa sincera: até onde cada um quer ir e o que está disposto a fazer (\u201cmapinha\u201d interno).",
        "Edmilson e Rose, com a direção", "Imediato"],
       ["Fazer o exercício vaca/bezerro/pasto",
        "Individualmente e em grupo, identificando o produto do presente, o do futuro próximo e o de longo prazo.",
        "Famílias e associação", "Imediato"],
       ["Conversar com Helenice e Bruno, da BrazilFoundation",
        "Pedir o apoio de alguém com trânsito acadêmico e institucional para fazer pontes e ajudar a estruturar "
        "projeto.", "Edmilson", "Curto prazo"],
       ["Retomar a relação com a Fundação Gaia",
        "Apresentar projetos, buscar intermediação para captação e verificar se a fundação tem registros "
        "históricos perdidos nas enchentes.", "Edmilson", "Curto prazo"],
       ["Fazer ponte com Domingos Armani e com o Centro Ecológico",
        "Eduardo pode apresentar; o contato com Domingos Armani já foi iniciado por Edmilson.",
        "Eduardo (contato) e Edmilson (acompanhamento)", "Curto prazo"],
       ["Procurar cursos de turismo, gastronomia, nutrição e engenharia de alimentos",
        "Falar com centros acadêmicos e professores; abrir espaço para estágios e assessoria.",
        "Associação", "Curto prazo"],
       ["Publicar chamada para estudantes de história",
        "Anunciar tema de trabalho de conclusão sobre a memória do assentamento.", "Associação", "Curto prazo"],
       ["Organizar encontro com os jovens",
        "Festa ou roda de conversa no fim do ano, durante as férias de Natal.", "Associação",
        "Curto prazo (dezembro)"],
       ["Visitar a Acolhida na Colônia, em Santa Catarina",
        "Conhecer o modelo e conversar com a professora indicada pelos financiadores.", "Quem puder viajar",
        "Médio prazo"],
       ["Fazer orçamento da agroindústria e da estrutura de acolhida",
        "Levantar custos de equipamentos e do telhado antes de pedir recurso.",
        "Associação, com apoio dos jovens", "Curto/médio prazo"],
       ["Montar material institucional e página",
        "Reunir história, produtos e contatos, usando o logo já existente.", "Associação, com Maria",
        "Curto/médio prazo"],
       ["Registrar as lições de cada visita e evento",
        "Manter um caderno de acertos e erros.", "Associação", "Contínuo"],
       ["Marcar nova conversa com Eduardo",
        "Compartilhar o que o grupo construir; ele deve ir ao Rio Grande do Sul no início do ano.",
        "Edmilson", "Curto prazo"]],
      [5.0, 6.6, 2.7, 1.7], font_size=8.5, first_col_bold=True)

callout(doc, "Antes de qualquer coisa", [
    "Definir no máximo três prioridades. \u201cSe ficar querendo fazer tudo, não se faz nada\u201d: a associação "
    "não é homogênea e o avanço vem de grupos menores, com quem de fato quer tocar cada frente.",
])

# ---------------- 7. LINHA DO TEMPO ----------------
h1(doc, "7", "Linha do tempo da conversa", page_break=True)
p(doc, "Os horários abaixo se referem ao bloco principal da gravação, com 1h08. Os dois blocos anteriores — "
       "tomados por falhas de conexão e retomadas — tratam dos mesmos assuntos e não foram transcritos em separado. "
       "Todos os horários são aproximados.", after=6)

table(doc,
      ["Horário", "Assunto", "Síntese"],
      [["01:56", "Sazonalidade",
        "De dezembro a fevereiro a renda cai; o Sítio do Tigre tem eventos agendados até janeiro, mas \u201ctem que "
        "ter algo a mais\u201d."],
       ["03:22", "Juventude e cultura",
        "A associação discute incluir o jovem e mudar uma cultura de trabalho consolidada na produção e na feira."],
       ["03:56", "Aprender a receber",
        "Receber alguém na propriedade exige preparo e capricho; não é simplesmente mandar vir."],
       ["04:25", "Enchentes e clima",
        "Episódios de enchente cada vez mais frequentes; necessidade de adaptação."],
       ["04:51", "Alternativas para o período ruim",
        "Comparação com o defeso da pesca no Ceará; preparar as atividades com antecedência."],
       ["05:53", "Disponibilidade",
        "Ninguém tem o mesmo ritmo nem a mesma disponibilidade; a associação precisa de clareza sobre isso."],
       ["07:35", "Eventos",
        "São Paulo tem eventos todo fim de semana; é preciso divulgação, organização e objetivo definido."],
       ["08:35", "Registro de lições",
        "Sugestão do caderno: anotar o previsto, o realizado e o imprevisto."],
       ["09:48", "Diagnóstico do grupo",
        "\u201cTemos que buscar alternativas, mas estamos meio perdidos\u201d; a identidade rural e agroecológica é "
        "uma riqueza que o próprio grupo subestima."],
       ["10:46", "Demanda e limite",
        "Há demanda de parceiros urbanos por visitas, mas falta transformar isso em atração, trabalho e renda."],
       ["11:53", "Contêineres",
        "Aquisição de contêineres no projeto com a BrazilFoundation; possibilidade de virarem espaço de acolhida."],
       ["12:11", "Falta de caixa",
        "\u201cEu hoje não tenho cinco mil reais para botar um telhadinho\u201d; todos estão na mesma situação."],
       ["12:38", "Rede de chefs",
        "Campanha de restaurantes e chefs de São Paulo na enchente; ideia de evento com chefs locais."],
       ["13:54", "Foco",
        "Muitas alternativas geram dispersão: escolher duas ou três coisas e batalhar por elas."],
       ["16:50", "Marca",
        "Conhecer o lugar é decisivo, mas é preciso marca para que o interesse não se disperse."],
       ["17:30", "Escala e processamento",
        "Visitas pagas; conservas, geleias e produtos diferenciados."],
       ["19:20", "Objetividade nos eventos",
        "Definir o que se quer do evento (\u201cque cinco pessoas saiam comprometidas\u201d)."],
       ["20:12", "Casos de turismo rural",
        "Experiências em Santa Catarina e no interior de Pernambuco, com envolvimento de jovens."],
       ["21:17", "Fontes de recurso",
        "Programas de turismo, BrazilFoundation, Fundo Casa e rede de cerca de vinte organizações."],
       ["23:23", "Embaixadas e assessorias",
        "Fundos de embaixadas para pequenas organizações; escritórios que elaboram projetos por percentual."],
       ["24:25", "Universidades",
        "Procurar cursos de turismo, gastronomia, nutrição e engenharia de alimentos; exemplo do CEPAGRO, na UFSC."],
       ["26:50", "Distância com as entidades",
        "Dificuldade de ponte entre quem produz e a universidade e as organizações; agricultura familiar em momento "
        "crítico."],
       ["31:33", "História da associação",
        "Origem do nome José Lutzenberger; saída do arroz com veneno; agroecologia; primeiro assentamento do Rio "
        "Grande do Sul a produzir orgânico."],
       ["36:17", "Visita dos financiadores",
        "A BrazilFoundation sugeriu a visita a Santa Catarina; o turismo ainda está concentrado no Sítio do Tigre e "
        "não é ação do coletivo."],
       ["38:36", "Mapa de interesses",
        "Necessidade de mapear o que cada família quer; diagnóstico do que é prioritário."],
       ["40:23", "Vaca, bezerro e pasto",
        "Exercício de curto, médio e longo prazo, individual e coletivo."],
       ["43:51", "Pomar e desidratados",
        "Cem pés de frutíferas nativas; colher-pague; mini agroindústria de desidratados."],
       ["45:32", "Cestas e referências",
        "Modelo de cestas; limão desidratado trazido da Itália."],
       ["47:19", "Nova geração",
        "Isabela, de quatorze anos, se interessa pelas frutas; os jovens podem se envolver."],
       ["48:48", "Qualidade de vida",
        "Manter os jovens no campo, com trabalho e vida melhores do que na cidade."],
       ["49:40", "Encontro com jovens",
        "Reunião só com os jovens no fim do ano; o trabalho remoto abre possibilidades."],
       ["51:24", "Memória",
        "Resgate da história do assentamento com estudantes; perda de fotos e documentos nas enchentes."],
       ["53:48", "Chamada para estudantes",
        "Anúncio para alunos de história em conclusão de curso."],
       ["54:50", "Fundação Lutzenberger",
        "Verificar a fundação e os centros de estudo ligados ao nome; a filha de Lutzenberger é cliente da feira."],
       ["57:46", "Durabilidade",
        "\u201cEm três dias já está estragado\u201d: processar para dar durabilidade ao que se produz."],
       ["01:00:49", "Fechamento",
        "Escolher três coisas; a associação não é homogênea; festa dos jovens; nova conversa quando quiserem."],
       ["01:04:22", "Encerramento",
        "Agradecimentos, convite para Eduardo conhecer o assentamento e sinalização de um novo encontro."]],
      [1.9, 4.6, 9.5], font_size=8.5)

# ---------------- 8. NOMES ----------------
h1(doc, "8", "Nomes, instituições e lugares citados", page_break=True)
p(doc, "Glossário do que aparece na conversa, para quem não participou e para quem retomar o assunto mais tarde.",
  after=6)

table(doc,
      ["Nome", "O que é e por que foi citado"],
      [["Associação José Lutzenberger",
        "Associação do assentamento da reforma agrária em Alvorada (RS), criado no final de 1991 e homenageado com "
        "o nome do ambientalista."],
       ["José Lutzenberger",
        "Ambientalista gaúcho que dá nome à associação; sua atuação com assistência técnica marcou a conversão do "
        "assentamento à agroecologia."],
       ["Sítio do Tigre",
        "Espaço de eventos ligado a uma das famílias, com agenda de casamentos e aniversários; hoje concentra o "
        "projeto de turismo rural."],
       ["Feira de sábado",
        "Feira semanal do grupo, principal canal de venda e ponto de contato com os consumidores urbanos."],
       ["Acolhida na Colônia",
        "Programa catarinense de turismo rural que transformou secadores de fumo em pequenas pousadas; usado como "
        "referência e sugerido como visita técnica."],
       ["Centro Ecológico",
        "Organização de agroecologia que atua na região de Torres e São Pedro, com lojas e projetos junto a "
        "assentamentos."],
       ["CEPAGRO",
        "Organização não governamental com sede junto à UFSC, que liderou a criação da Rede Ecovida; citada como "
        "exemplo de articulação dentro da universidade."],
       ["Rede Ecovida",
        "Rede de agroecologia criada nos anos 1990 no Sul do país, com participação de Eduardo desde o início."],
       ["BrazilFoundation",
        "Fundação que apoia a associação — inclusive com os contêineres — e que pode intermediar apoio técnico; "
        "Helenice e Bruno acompanham o grupo."],
       ["Fundo Casa",
        "Fundo socioambiental citado como uma entre cerca de vinte organizações semelhantes no país."],
       ["Fundação Gaia",
        "Fundação ligada à família de José Lutzenberger, com sítio aberto a visitações em município vizinho; "
        "apontada como possível parceira na captação de recursos e detentora de registros históricos."],
       ["Domingos Armani",
        "Referência de Porto Alegre em projetos sociais e agroecologia, citada como possível ponte institucional; o "
        "contato já foi iniciado."],
       ["Instituto Saúde e Alegria",
        "Organização ligada à experiência de visitação no rio Tapajós, usada como exemplo de como uma visita pode "
        "virar parceria duradoura."],
       ["Sebrae e Ministério do Turismo",
        "Fontes de apoio técnico e de programas com recursos para infraestrutura turística."],
       ["Embaixadas",
        "Japão, Inglaterra, Canadá, Alemanha e Suíça: mantêm fundos para doação a pequenas organizações."],
       ["Programas de aquisição de alimentos",
        "Compras públicas de alimentos para escolas e hospitais (federais, estaduais e municipais), citadas sem "
        "fixar sigla; incluem o Programa de Aquisição de Alimentos e o Programa Nacional de Alimentação Escolar."],
       ["Porto Alegre, Alvorada, Torres, São Pedro, Passo Fundo",
        "Referências de localização usadas na conversa, no Rio Grande do Sul."],
       ["Maria", "Fez o logo do grupo e estuda em São Paulo; citada como possível apoio na comunicação."],
       ["Marines, Isabela, Luiz Felipe e Gabriel",
        "Jovens e famílias citados ao tratar da sucessão e do interesse da nova geração."]],
      [4.4, 11.6], font_size=8.5, first_col_bold=True)

# ---------------- 9. OBSERVAÇÕES ----------------
h1(doc, "9", "Observações sobre a gravação")
p(doc, "A gravação chegou em três blocos, com problemas técnicos no começo: imagem e som intermitentes, quedas de "
       "conexão e tentativas de reconexão. Boa parte do início foi transcrita de forma imprecisa — trechos "
       "ininteligíveis, frases soltas e passagens em outro idioma geradas pelo reconhecimento automático de voz. "
       "Por isso este documento não reproduz a transcrição literal: reconstrói o conteúdo em português, com edição "
       "leve, e suprime tudo o que não tinha conteúdo em português.", after=7)

p(doc, "Onde a transcrição era ambígua, o assunto foi omitido ou tratado como aproximação; nomes próprios foram "
       "normalizados. Se algum ponto for decisivo para uma decisão, vale conferir na gravação original.", after=7)

p(doc, "A conversa terminou com agradecimentos mútuos, convite para Eduardo conhecer o assentamento — com peixe e "
       "carne de porco, \u201cnão pode negar\u201d — e a sinalização de uma nova conversa, quando o grupo tiver "
       "amadurecido as ideias. Eduardo mencionou que deve ir ao Rio Grande do Sul no início do ano, com passagem por "
       "Passo Fundo, e elogiou a participação das duas integrantes do grupo, que segundo ele honraram a associação "
       "em um conjunto de dez associações acompanhadas.", after=10)

p(doc, "Documento organizado em 9 de novembro de 2026, a partir da transcrição automática da reunião.",
  size=8.5, color=GREY, italic=True, align=WD_ALIGN_PARAGRAPH.LEFT, before=8)

# propriedades
cp = doc.core_properties
cp.title = "Assessoria à Associação José Lutzenberger — compilação temática da reunião de 9 de novembro de 2026"
cp.subject = "Compilação temática da transcrição da reunião"
cp.author = "Associação José Lutzenberger"
cp.language = "pt-BR"
cp.comments = ("Documento gerado a partir da transcrição automática da reunião. Conteúdo integralmente em "
               "português do Brasil; trechos sem conteúdo em português foram suprimidos.")
cp.keywords = "reunião; agroecologia; comercialização; turismo rural; assentamento"

doc.save(OUT)
print("ok:", OUT)

