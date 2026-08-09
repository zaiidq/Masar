# Masar — Gemini integration setup

Copy every file in this folder into `C:\xampp\htdocs\masar`, keeping the
folder structure. Then:

## 1. Run the migration

```
mysql -u root masar_db < database/migrations/2026_08_07_add_ai_analysis_tables.sql
```

Or import that file from phpMyAdmin with `masar_db` selected.

This adds `record_courses` and `record_recommendations`, plus
`ai_model`, `prompt_version`, and `analysis_json` on `academic_records`.

## 2. Create the API key

Get a key from https://aistudio.google.com/app/apikey, then create
`masar/.env` (copy `.env.example`):

```
GEMINI_API_KEY=AIza...
GEMINI_MODEL=gemini-2.5-flash
```

`.env` is already covered by `.gitignore`. Never commit it.

## 3. Enable cURL

Open `C:\xampp\php\php.ini`, find `;extension=curl`, remove the leading
semicolon, and restart Apache.

## 4. Test from the command line first

```
cd C:\xampp\htdocs\masar
C:\xampp\php\php.exe tools/test-analysis.php "path\to\record.pdf"
```

That runs parsing only, and prints the reconstructed table rows. If the
rows look aligned, add the AI step:

```
C:\xampp\php\php.exe tools/test-analysis.php "path\to\record.pdf" --gemini
```

It prints the course counts by state and every recommendation, marked
`[OK]` or `[REJECT]`. Testing here rather than through the browser
separates parsing problems from database and session problems.

## 5. Use it

Upload a record from the student portal. Analysis runs on upload, then
`Recommendations` in the sidebar shows the results. That link already
existed in the sidebar but had no page behind it — `recommendations.php`
fills it.

## Notes

- Analysis runs synchronously and takes roughly 10–30 seconds. If PHP
  times out, raise `max_execution_time` in `php.ini`.
- Student name, university ID, advisor, and nationality are stripped
  before the text is sent to Google.
- Rejected recommendations are stored, not discarded. Querying
  `record_recommendations WHERE is_accepted = 0` gives you real numbers
  on how often the model suggested something invalid — useful evidence
  for the evaluation chapter.
