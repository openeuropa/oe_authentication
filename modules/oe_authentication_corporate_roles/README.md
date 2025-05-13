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

## How it works

With this module, you can create "Corporate role mappings" (/admin/people/corporate-roles-mapping) that allow you to preconfigure which roles users get when they log in to the website using EU Login. You can match the user by:

* LDAP group -> a simple string comparison to determine if the user is in a given LDAP group
* Department -> for example DIGIT.B.3 will include all users in DIGIT.B.3 (DIGIT.B.3.01, DIGIT.B.3.02, etc). You can go granular (for ex DIGIT.B.3.02) or very wide (for example DIGIT which will include everyone in DIGIT)

You can create any number of mappings and when the users log in/register, the roles across any of the mappings they match with, get assigned to them. Every time they log back in, this check is done again to ensure they didn't change their LDAP group/department.

Morever, when you create/update/delete the corporate role mappings, all the users on the site get updated with the corresponding roles they should match with.
