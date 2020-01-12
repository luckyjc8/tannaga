git add .
if ["%~1"] == [""] (git commit -m "commit") else (git commit -m "%~1")
