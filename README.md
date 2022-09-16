# plg_content_qlmodule

The content plugin enables you to call any published module you generates and display it in your article.
Menu assignments have not to be set anymore.

## Basic usage

Insert following tag into the article. The module with that very id will be loaded into article.

~~~shell
{qlmodule qlmoduleId="HERE_YOUR_MODULE_ID" /}
~~~

## Setting params

Module params can be set within the tags, e. g. 

~~~shell
{qlmodule qlmoduleId="123" user="2" query="SELECT * FROM #__something" /}
~~~

Reallife Example:

* Say, you have a custum Contact module as a kinda template
* use {qlmodule qlmoduleId="CONTACT_MODULE_ID" contact_id="CONTACT_ID" /} to display the contact within article; "contact_id" ist the module parameter that you overwrite/set within the very tag
* thus you can have ONE module with its modules settings ruling ALL entities of the qlmodule

Further Settings:

* In case a module param asks for a linebreak, set a double tilde (~~).
* If you need a json within param or an array, use param="JSON['first','second']", add alternating variables {qlmodule qlmoduleId="HERE_YOUR_MODULE_ID" codeParams="{'numContactId':'13','strClass':'one_third'}" /}; codeParams can be caught by "$codeParams"
* As params can be set - though limitedly - for different callings. E. g. so you need only 1 generated module for 5 callings, just set params within tag.
