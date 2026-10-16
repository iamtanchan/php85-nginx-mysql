-- Widen login.pass so it can hold password_hash() output (bcrypt is 60 chars;
-- PHP recommends 255 for future algorithms).
--
-- Safe to run before or after deploying the password-hashing code: until this
-- runs, logins keep working with the existing plaintext passwords; afterwards,
-- each account is upgraded to a hash on its next successful login.
ALTER TABLE `login` MODIFY `pass` varchar(255) DEFAULT NULL;
