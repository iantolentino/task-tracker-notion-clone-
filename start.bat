@echo off
echo Installing Flask if needed...
pip install flask --quiet
echo.
echo Starting Tasks Tracker...
echo Open http://localhost:5000 in your browser
echo Press Ctrl+C to stop the server.
echo.
python app.py
pause
