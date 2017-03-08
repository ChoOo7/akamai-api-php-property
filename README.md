akamai-php-property
===================

Expose Services & cli command to manage configurations

Actually supported : 

List groups
List properties
Get rules of a property
Set rules of a property
Create a new version of a property
A working example of bulk editing all properties of my contract to add some behavior on a particular place a the tree.



Lists commands

 akamai
  akamai:createversion
  akamai:getgroups
  akamai:getproperties
  akamai:getrule
  akamai:setrule
  akamai:updateallproperties

Example :

php bin/console akamai:updateallproperties --contract ctr_F-67IJDG