# AI Contract Copilot — PHP Version 🐘

Backend PHP untuk analisa kontrak hukum Indonesia pake DeepSeek AI.

## Cara Install di Hostinger

1. **Upload file ke hosting** via FTP / cPanel File Manager
2. **SSH ke hosting** atau buka **Terminal** di cPanel
3. **Jalanin composer:**
   ```bash
   cd ~/public_html/subdomain-anda
   composer install --no-dev
   ```
4. **Bikin .env** — copy `.env.example` jadi `.env`, isi `DEEPSEEK_API_KEY`
5. **Bikin folder** `templates/` dan upload `index.html` ke dalamnya

## Struktur File
```
├── index.php         ← Main router
├── config.php        ← Konfigurasi & helper
├── parser.php        ← Parse PDF/DOCX
├── analyzer.php      ← DeepSeek API integration
├── database.php      ← SQLite database
├── composer.json     ← Dependensi PHP
├── .env              ← API key (jangan di-commit!)
└── templates/
    └── index.html    ← Frontend (copy dari Python version)
```

## Endpoints
| Method | Path | Deskripsi |
|--------|------|-----------|
| GET | `/` | Serve frontend |
| POST | `/analyze` | Upload + analisa kontrak |
| POST | `/generate` | Generate draft dokumen |
| GET | `/history` | Riwayat analisa & draft |
| GET | `/health` | Health check |
