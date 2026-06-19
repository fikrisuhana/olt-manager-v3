-- Migration 006: tambah kolom pppoe_pass di tabel onus
ALTER TABLE onus ADD COLUMN pppoe_pass VARCHAR(100) NULL AFTER pppoe_user;
