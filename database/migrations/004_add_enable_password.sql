-- Tambah kolom enable_password ke tabel olts
-- Opsional: diisi jika OLT memerlukan password saat masuk privileged mode (enable)
-- Jika kosong, enable tetap dikirim tapi tanpa password

ALTER TABLE olts
    ADD COLUMN enable_password VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Password untuk perintah enable (privileged mode). Opsional.'
    AFTER telnet_pass;
