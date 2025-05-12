# Neos Content Plugin

This plugin enables Shopware editors to use the Neos Editing Experience in Shopware.


### Notes

in content.json there is a example cms page in json format.

### Permissions
We can add roles and linked permissions to restrict/enable editing neos content in shopware.
Example permission for neosAdmin can be found in [`Resources/app/administration/src/NeosRoles/neosAdmin/index.js`](./src/Resources/app/administration/src/NeosRoles/neosAdmin/index.js).
To protect some paths in Shopware we have to change JS like shown [here](https://developer.shopware.com/docs/guides/plugins/plugins/administration/permissions-error-handling/add-acl-rules.html#protect-your-plugin-routes).
