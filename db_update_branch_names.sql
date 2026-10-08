-- Update branch location names to requested values
UPDATE locations SET name = 'ام خنان' WHERE name = 'فرع 1';
UPDATE locations SET name = 'المنوات' WHERE name = 'فرع 2';
-- If IDs known, you can also run:
-- UPDATE locations SET name = 'ام خنان' WHERE id = 2;
-- UPDATE locations SET name = 'المنوات' WHERE id = 3;
