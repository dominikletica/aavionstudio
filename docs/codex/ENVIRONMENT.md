# Environment Notes
OS: MacOs Tahoe 26.1  
Shell: /bin/zsh  
CLI-Tools: /opt/homebrew/bin  
Repository root: /Users/letica/Library/Mobile Documents/com~apple~CloudDocs/Repository/aavionstudio  

## Paths & Interpreters
- php: /opt/homebrew/bin/php - Vesion: 8.4.14
- python3: /usr/bin/python3 - Version: 3.14.0
- composer: /opt/homebrew/bin/composer - Version 2.8.12
- symfony: /opt/homebrew/opt/symfony-cli/bin/symfony - Version: 5.15.1
- node: /opt/homebrew/bin/node - Version: 24.10.0
- npm: /opt/homebrew/bin/npm - Version: 11.6.0
- perl: /usr/bin/perl - Version: 5.34.1

## Helpful CLI patterns
- Lint PHP file: php -l <path>

## References
- Composer dependencies: composer.json / composer.lock (if present)
- Node dependencies: package.json / package-lock.json (if present)
- Worklog & notes: docs/codex/WORKLOG.md
- Additional notes: docs/codex/notes/*.md
- Documentation: docs/ (always keep aligned with codebase - both user and developer docs)

## Toolbox
Reusable scripts should live directly under `.codex/` (e.g. `.codex/scripts/` or `.codex/snippets/`). Add helpers here to avoid re-writing shell snippets.
