cakephp-2.x-datasources
=======================

These are fully functional datasources although most will require some additional drivers installed in php.

DataSource/OdbcSource.php - Base class for odbc connections, you will need to get the connections configured to work from the command line first. Each database will need at minimum ts own datasource with listSources and describe functions   
DataSource/InformisOdbx.php - Extends OdbcSource for informix databases primarily by  providing listSources and describe functions
DataSource/Database/Oracle.php - derived from the cake repos version (note I found this here https://groups.google.com/forum/#!topic/cake-php/hNV9Vb9ZbUc)  
DataSource/Database/OracleOci.php - derived from the cake repos version with major changes to more closely duplicate the DboSource functions 
DataSource/Database/OraclePdoOci.php - uses pdo_oci so it is even more closely integrated with DboSource

Why three Oracle Sources? Which should I use?  
Well if you want something that is going to be the most compatible going forward use OraclePdoOci.php since it uses PDO it is most simialr to the "officially" supported datasources.  
Oracle.php has a lot of functionality that may or may not be valuable. It retains most of the functions form the 1.3 driver. 
OracleOci.php is a good compromise of retaining the previously created functions and trying to behave more like the supported data sources.




1. For Oracle DataSources (all three work you only need one)
Centos: Follow the instructions here:
http://en.kioskea.net/faq/4987-linux-redhat-oracle-installing-pdo-oci-and-oci8-modules

Others: Google is your friend

2. For Odbc first get the odbc cli client working
TODO: add intructions
