#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Script para traduzir descrições dos skills GSD para português brasileiro.
Usa a API MyMemory para tradução (gratuita, sem autenticação necessária).
"""

import os
import re
import json
import requests
from pathlib import Path
from typing import Dict, Tuple

# API de tradução gratuita
TRANSLATION_API = "https://api.mymemory.translated.net/get"

def translate_text(text: str, source_lang: str = "en", target_lang: str = "pt-br") -> str:
    """
    Traduz texto usando a API MyMemory.
    
    Args:
        text: Texto a traduzir
        source_lang: Idioma de origem (padrão: en)
        target_lang: Idioma de destino (padrão: pt-br)
    
    Returns:
        Texto traduzido
    """
    try:
        params = {
            "q": text,
            "langpair": f"{source_lang}|{target_lang}"
        }
        response = requests.get(TRANSLATION_API, params=params, timeout=10)
        
        if response.status_code == 200:
            data = response.json()
            if data.get("responseStatus") == 200:
                return data.get("responseData", {}).get("translatedText", text)
    except Exception as e:
        print(f"Erro na tradução: {e}")
    
    return text

def extract_description(content: str) -> Tuple[str, str]:
    """
    Extrai a descrição do cabeçalho YAML.
    
    Args:
        content: Conteúdo do arquivo
    
    Returns:
        Tupla (descrição_original, resto_do_conteúdo)
    """
    # Busca o padrão description: "..." no cabeçalho YAML
    match = re.search(r'description:\s*"([^"]+)"', content)
    if match:
        return match.group(1), content
    return None, content

def update_skill_description(file_path: str, new_description: str) -> bool:
    """
    Atualiza a descrição em um arquivo SKILL.md.
    
    Args:
        file_path: Caminho do arquivo
        new_description: Nova descrição traduzida
    
    Returns:
        True se atualizado com sucesso
    """
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Substitui a descrição original pela traduzida
        new_content = re.sub(
            r'description:\s*"[^"]+"',
            f'description: "{new_description}"',
            content
        )
        
        # Escreve de volta com encoding UTF-8
        with open(file_path, 'w', encoding='utf-8') as f:
            f.write(new_content)
        
        return True
    except Exception as e:
        print(f"Erro ao atualizar {file_path}: {e}")
        return False

def main():
    """Função principal."""
    skills_dir = Path.home() / ".gemini" / "antigravity" / "skills"
    
    if not skills_dir.exists():
        print(f"❌ Diretório não encontrado: {skills_dir}")
        return
    
    print(f"📂 Processando skills em: {skills_dir}")
    print("=" * 80)
    
    # Lista de skills
    skill_dirs = sorted([d for d in skills_dir.iterdir() if d.is_dir()])
    total_skills = len(skill_dirs)
    successful = 0
    failed = 0
    
    # Dicionário para armazenar log das traduções
    translations_log = {}
    
    for idx, skill_dir in enumerate(skill_dirs, 1):
        skill_file = skill_dir / "SKILL.md"
        
        if not skill_file.exists():
            print(f"⏭️  [{idx}/{total_skills}] {skill_dir.name} - SKILL.md não encontrado")
            failed += 1
            continue
        
        try:
            # Lê o arquivo
            with open(skill_file, 'r', encoding='utf-8') as f:
                content = f.read()
            
            # Extrai a descrição
            original_desc, _ = extract_description(content)
            
            if not original_desc:
                print(f"⏭️  [{idx}/{total_skills}] {skill_dir.name} - Descrição não encontrada")
                failed += 1
                continue
            
            # Traduz
            print(f"🔄 [{idx}/{total_skills}] {skill_dir.name}")
            print(f"   EN: {original_desc[:70]}{'...' if len(original_desc) > 70 else ''}")
            
            translated_desc = translate_text(original_desc)
            print(f"   PT: {translated_desc[:70]}{'...' if len(translated_desc) > 70 else ''}")
            
            # Atualiza o arquivo
            if update_skill_description(str(skill_file), translated_desc):
                print(f"   ✅ Atualizado com sucesso")
                successful += 1
                translations_log[skill_dir.name] = {
                    "original": original_desc,
                    "translated": translated_desc
                }
            else:
                print(f"   ❌ Erro ao atualizar")
                failed += 1
        
        except Exception as e:
            print(f"   ❌ Erro: {e}")
            failed += 1
        
        print()
    
    # Resumo
    print("=" * 80)
    print(f"📊 RESUMO")
    print(f"   Total de skills: {total_skills}")
    print(f"   ✅ Traduzidos com sucesso: {successful}")
    print(f"   ❌ Falhados: {failed}")
    print()
    
    # Salva o log de traduções
    log_file = Path.home() / ".gemini" / "antigravity" / "skills" / "TRADUCOES_LOG.json"
    try:
        with open(log_file, 'w', encoding='utf-8') as f:
            json.dump(translations_log, f, ensure_ascii=False, indent=2)
        print(f"📝 Log de traduções salvo em: {log_file}")
    except Exception as e:
        print(f"⚠️  Erro ao salvar log: {e}")

if __name__ == "__main__":
    main()
