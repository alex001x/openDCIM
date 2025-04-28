---
--- Schema changes for 23.04 to 24.01
---

UPDATE fac_Config set Value="24.01" WHERE Parameter="Version";

ALTER TABLE fac_People ADD COLUMN AdminTemplateModel TINYINT(1) DEFAULT 0;
ALTER TABLE fac_People ADD COLUMN AdminImage TINYINT(1) DEFAULT 0;
