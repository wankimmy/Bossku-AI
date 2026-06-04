# BosskuAI Desktop

Hermes-style Windows desktop app: thin installer, full stack on first launch.

## Modes

| Mode | How |
|---|---|
| **Native (default)** | Packaged `.exe` or `npm start` — portable PHP + Node, SQLite, no Docker |
| **Docker** | `BOSSKU_DESKTOP_RUNTIME=docker npm start` — requires Docker Desktop + compose stack |

## Data locations (native)

- `%LOCALAPPDATA%\BosskuAI\stack` — app copy
- `%LOCALAPPDATA%\BosskuAI\runtime` — PHP, Node, Git
- `%LOCALAPPDATA%\BosskuAI\data\bossku.sqlite` — database
- `%LOCALAPPDATA%\BosskuAI\logs\desktop.log` — logs

## Build installer

```powershell
cd desktop
npm install
npm run dist
```

Output: `dist/BosskuAI Setup 3.0.0.exe`
