-- Add vlan_profiles column to olts table
ALTER TABLE olts ADD COLUMN vlan_profiles TEXT NULL AFTER traffic_profiles;
