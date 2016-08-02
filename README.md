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

The API requires client access to the eCampus Kind API from UNINETT (https://github.com/skrodal/ecampus-kind-api). 
As such, it will need client credentials to communicate with this API (see 'suitable client' below).   

## Suitable Client

A client that makes use of the API is available here: https://github.com/skrodal/techsmith-relay-register-client

When this client is registered with Dataporten, its client credentials may be used by this API to talk to the ecampus-kind-api.

## Create user

See Relay\Router for available routes. When the `create` route is called, the user account will be generated from credentials delivered by Dataporten.

Checks will be made to ensure that the account does not already exist and with Kind to ensure org subscription and affiliation access. If successful, 
the account will be generated with the correct profiles, roles and a random password. An email will be sent to the user with information on how to get started. 

## Dependencies

- UNINETT Dataporten
- Alto Router
- PEAR Mail

— by Simon Skrødal, 2016
 