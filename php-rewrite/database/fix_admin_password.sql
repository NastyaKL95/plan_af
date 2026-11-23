-- Если вход admin@iro.local не работает, выполните в MySQL (пароль: password)
UPDATE users
SET password_hash = '$2b$10$fWK4HOxI/72avHz7UUwPZOzasp5dJxW7EkkOkRodrzwcXXvAGjP6O'
WHERE LOWER(TRIM(email)) = 'admin@iro.local';
