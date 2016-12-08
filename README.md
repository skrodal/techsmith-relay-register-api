# TechSmith Relay User Registration

**NOTE: Created to suit higher education in Norway; makes use of Dataporten (UNINETT) for client/user (O)Authentication.** 

This API facilitates self-service account registration with TechSmith Relay (Self-Hosted instance).

## Installation

- Clone the repository
- Register the API in Dataporten and request the following scopes:
    - `email`, `groups`, `profile`, `userid`, `userid-feide`
- Create a Client Scope named `admin`
- Populate the various config files in /etc (move them away from public html area) 
- Update path pointers in /relay/config.php

NOTE: Use of Kind is deprecated since 08.12.2016
~~The API requires client access to the eCampus Kind API from UNINETT (https://github.com/skrodal/ecampus-kind-api). 
As such, it will need client credentials to communicate with this API (see 'suitable client' below).~~   

## Checking org access

The API was updated on 08.12.2016 to remove any dependencies on Kind. Instead, the API reads org access/affiliation 
from a simple (MySQL) table (currently hosted on UNINETTs MySQL Cluster).

To reproduce this table, which needs to be updated manually when new orgs subscribe to the service;
   
```sql
    CREATE TABLE `relay_subscribers` (
    `org` varchar(30) NOT NULL,
    `affiliation_access` varchar(10) NOT NULL,
    PRIMARY KEY (`org`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```
   	
...where affiliation_access should be 'employee' or 'member' (for employee and student access).

Some starter values (orgs found in Kind for the service at the time of writing):
   
```sql
    INSERT INTO `relay_subscribers` (`org`, `affiliation_access`)
    VALUES
        ('aho.no', 'employee'),
        ('forskningsradet.no', 'member'),
        ('hbv.no', 'member'),
        ('hials.no', 'employee'),
        ('hig.no', 'employee'),
        ('hih.no', 'member'),
        ('hihm.no', 'employee'),
        ('hinesna.no', 'member'),
        ('hioa.no', 'member'),
        ('hiof.no', 'member'),
        ('hit.no', 'employee'),
        ('hivolda.no', 'employee'),
        ('ldh.no', 'employee'),
        ('mf.no', 'employee'),
        ('mhs.no', 'member'),
        ('nhh.no', 'employee'),
        ('nih.no', 'member'),
        ('nmbu.no', 'member'),
        ('ntnu.no', 'employee'),
        ('samiskhs.no', 'employee'),
        ('uia.no', 'member'),
        ('uib.no', 'employee'),
        ('uin.no', 'member'),
        ('uio.no', 'member'),
        ('uit.no', 'member'),
        ('umb.no', 'employee'),
        ('uninett.no', 'employee');
```


## Suitable Client

A client that makes use of the API is available here: https://github.com/skrodal/techsmith-relay-register-client

~~When this client is registered with Dataporten, its client credentials may be used by this API to talk to the ecampus-kind-api.~~

## Create user

See Relay\Router for available routes. When the `create` route is called, the user account will be generated from credentials delivered by Dataporten.

Checks will be made to ensure that the account does not already exist ~~and with Kind~~ and with MySQL table to ensure org subscription and affiliation access. If successful, 
the account will be generated with the correct profiles, roles and a random password. An email will be sent to the user with information on how to get started. 

## Dependencies

- UNINETT Dataporten
- Alto Router
- PEAR Mail

— by Simon Skrødal, 2016
 