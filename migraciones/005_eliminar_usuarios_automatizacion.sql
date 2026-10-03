-- Las pruebas funcionales antiguas escribían médicos en la base de revisión y no los eliminaban.
-- Los patrones incluyen un sello de tiempo y dominios reservados exclusivamente para automatización.
DELETE FROM usuarios
WHERE rol_codigo = 'MED'
  AND (
      correo REGEXP '^cypress_doc_[0-9]+@hospital\\.com$'
      OR correo REGEXP '^temp_login_[0-9]+@hospital\\.com$'
      OR (nombre = 'Doctor Temporal Login' AND correo REGEXP '^rf03\\.[0-9]+@example\\.test$')
      OR correo = 'test.auto@medico.com'
  );
