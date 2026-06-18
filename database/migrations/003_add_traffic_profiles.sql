-- Tambah kolom traffic_profiles ke tabel olts
-- traffic_profiles: daftar nama traffic/bandwidth profile di OLT (show gpon profile traffic)
-- Satu nama per baris, sama format dengan tcont_profiles

ALTER TABLE olts
    ADD COLUMN traffic_profiles TEXT NULL
    COMMENT 'daftar nama traffic profile di OLT (show gpon profile traffic), satu per baris'
    AFTER tcont_profiles;
