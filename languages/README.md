# Traduções / Translations

## Gerar o arquivo .mo

O WordPress requer arquivos `.mo` (binário compilado) para carregar traduções.

Para gerar a partir do `.po`:

```bash
# Com msgfmt (gettext tools)
msgfmt agentpress-pt_BR.po -o agentpress-pt_BR.mo

# Ou use o plugin Loco Translate no WordPress
# Ou use o editor Poedit (https://poedit.net/)
```

## Idiomas disponíveis

- 🇧🇷 Português (Brasil) — `pt_BR`

## Contribuir com traduções

1. Copie `agentpress-pt_BR.po` como `agentpress-{locale}.po`
2. Traduza as strings `msgstr`
3. Gere o `.mo`
4. Envie um PR
