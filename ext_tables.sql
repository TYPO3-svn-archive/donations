#
# $Id: ext_tables.sql 3450 2007-08-14 19:44:30Z fsuter $
#
# Table structure for table 'tx_donations_projects'
#
CREATE TABLE tx_donations_projects (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    crdate int(11) DEFAULT '0' NOT NULL,
    cruser_id int(11) DEFAULT '0' NOT NULL,
    deleted tinyint(4) DEFAULT '0' NOT NULL,
    hidden tinyint(4) DEFAULT '0' NOT NULL,
    starttime int(11) DEFAULT '0' NOT NULL,
    endtime int(11) DEFAULT '0' NOT NULL,
    fe_group int(11) DEFAULT '0' NOT NULL,
    title varchar(255) DEFAULT '' NOT NULL,
    logo blob NOT NULL,
    short_desc text NOT NULL,
    long_desc text NOT NULL,
    details_url tinytext NOT NULL,
    amount double(11,2) DEFAULT '0.00' NOT NULL,
    paid double(11,2) DEFAULT '0.00' NOT NULL,
    currency int(11) DEFAULT '0' NOT NULL,
    min_payment double(11,2) DEFAULT '0.00' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);



#
# Table structure for table 'tx_donations_deposits'
#
CREATE TABLE tx_donations_deposits (
    uid int(11) NOT NULL auto_increment,
    pid int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    crdate int(11) DEFAULT '0' NOT NULL,
    cruser_id int(11) DEFAULT '0' NOT NULL,
    project_uid int(11) DEFAULT '0' NOT NULL,
    cust_company tinytext NOT NULL,
    cust_name tinytext NOT NULL,
    cust_addr tinytext NOT NULL,
    cust_city tinytext NOT NULL,
    cust_zip tinytext NOT NULL,
    cust_country tinytext NOT NULL,
    cust_email tinytext NOT NULL,
    amount tinytext NOT NULL,
    paymentlib_trx_uid int(11) DEFAULT '0' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);
