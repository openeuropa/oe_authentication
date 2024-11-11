# OpenEuropa Authentication Corporate Roles

This submodule allows the configuration of mappings between user departments/LDAP groups and roles, in view of automatically assigning those roles to the users when they log in.

## Requirements

This module requires the following modules:
- [OE Authentication user fields](modules/user_authentication_user_fields)

## Installation

This module comes with a new entity reference field on the User entity called "Manual roles". The purpose of this field is to store on the user the roles which have been granted to the user "manually" (as opposed to automatically
via the logic of this module).

When installing the module, you will need to create an update path that goes through all the users on your site and sets the value of the "Manual roles" field to whatever roles the users have (apart from the default authenticated role).
Depending on the size of your site, you may want to batch this operation.

It is important to have these roles referenced so that when roles are unassigned automatically, the ones that have been assigned manually are not removed.
