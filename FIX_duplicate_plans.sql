-- Run this ONCE in phpMyAdmin -> SQL tab to remove the duplicate plan rows
-- caused by MIGRATION_ADD_pricing_payments.sql being run more than once.
-- Safe to run even if there are no duplicates (it just won't delete anything).

-- Keeps the lowest id for each (name, audience) pair, deletes the rest.
DELETE p1 FROM plans p1
INNER JOIN plans p2
  ON p1.name = p2.name
 AND p1.audience = p2.audience
 AND p1.id > p2.id;

-- Same cleanup for payment methods, in case that seed got duplicated too.
DELETE m1 FROM payment_methods m1
INNER JOIN payment_methods m2
  ON m1.type = m2.type
 AND m1.label = m2.label
 AND m1.id > m2.id;
