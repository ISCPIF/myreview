#
# Create the database. If you change any value here, report
# the changes in the config/database.ini file.
#

CREATE DATABASE myreview CHARACTER SET UTF8;

#
# Create a MySQL user. Change 'localhost' to the name of the server
# that hosts MySQL.
#

GRANT ALL PRIVILEGES ON myreview.* TO adminReview@localhost
       IDENTIFIED BY 'mdpAdmin';

#
# Create a MySQL user with restricted right for SQL queries
#

GRANT select ON myreview.*  TO SQLUser@localhost IDENTIFIED BY 'pwdSQL';
