#Nooku Router - Alternative

This project is an attempt to create a more flexible router for Nooku.

The router now is split into several parts. The main router contains the build and parse methods, and now holds a command chain object that holds several "rules". Rules are used to allow different routing methods, there are 5 rules by defaut:

1. cache - an APC cache that caches built and parsed rules
2. database - looks for routes in a database table and stores routes to the database if a cache flag is set in the * context
3. registry - build/parses routes based on static routes that are registered with the registry object. These routes are templated/defined routes.
4. component - builds/parses routes using the components router. 
5. page - builds/parses routes using the pages component

Rules are registered with a priority and roughly run in the order above. New rules can easily be added through the configuration parameters.

I think this solution provides more flexibility than the current router and allows for customization on a per project basis.

The new router can be dropped in by setting an alias

	KService::setAlias('com://site/application.router','com://site/router.router'); //for frontend routing
	
	and/or
	
	KService::setAlias('com://admin/application.router','com://site/router.router'); //for backend routing
	
	//N.B. admin does not use component/page/database rules

Run the supplied sql in sql/schema.sql to setup the DB tables

##Rules

###Cache

The cache rule allows for built and parsed routes to be loaded from/stored in APC. This speeds up routing as the rest of the rules then do not need to be run. The cache can be cleared by calling clearCache() on the main router `com://site/router.router`. An optional `type` parameter can be passed in the context to clear a specific type of cache. Either build or parse can be used to clear their respective cache.

###Database

The database rule attempts to build/match routes from entries stored in the `#__router_routes` table. If a valid match is found, its details will be used for the route and the build execution will stop.

###Registry

The registry rule attempts to build/parses routes based on static routes that are registered with the registry object. These routes are templated/defined routes that take on the following structure:

	'/component/view' => array('option' => 'component','view' => 'view')
	'/component/view/{id:\d*}' => array('option' => 'component', 'view' => 'view')
	
	For example:
	
	'/foo/bars' => array('option' => 'com_foo','view' => 'bars')
	'/foo/bar/{:id:\d*}' => array('option' => 'com_foo', 'view' => 'bar')
	
Parameters can be captured from the route by entering them in curly braces. The value after the first colon will be the resulting property name, the value after the second colon denotes a regular expression to use for building and matching the route. The route above will create an index of 'id' that has the value captured from the route as long as the value is a digit.
	
These routes can be registered in one of 3 ways:

Via a routes.json or a routes.php file in the root of the frontend component. 

1: When using the PHP file, one must call:  

	ComRouterRegistry::connect($template, $params);
	
	$template being the template for the route as defined above
	$params being the query params for the route
	
2: When using the json file

a. The json file must be structured as follows:
b. Be valid json (ensure you escape template regex expressions, eg `\\d` not `\d`)
c. Use properties of the root object as the route template, and 

You do not need to define 'option' within the parameters if the route is registered in one of the above 2 ways, the registry will know where the route is registered from. If however the route is registered outside of the component, option is required.

3: Via a routes.php file in the root of the application `/routes.php`. This file is loaded if it does not exist.

Routes are also cached in APC for speed. The cache can be cleared by calling clearCahe() on the main router. You can pass an optional `type` parameter to the clearCache method.

The 3 types of cache are:

* parse - parsed routes that return a successful internal route are cached
* match - matched routes that return a successful external route are cached
* routes - auto loaded routes are cached on registry creation. These routes are loaded from either a php or .json file in the component root folder, or if neither is found, created automatically based on the views/layouts.

###Component

The component rule attempts to build/parse routes using components built in routers. The routers can be used if fine grained control over the route can't be achieved by the other methods. The component router requires a `buildRoute()` and a `parseRoute()` method. The component rule also extends the page rule as the page rule is required in order for parsing to work correctly. The page rule must be invoked before the component rule when parsing routes.

The component rule will also check for a canCache() method on the router, if set the cache flag will be set in the context and the database rule will cache the route to the DB.

###Page

The page rule attempts to build/parse routes using the pages already defined in the database. Page matching is a fairly basic approach and attempts to match the route of a page to the route being parsed. It does so in reverse order to match the highest sublevels first.

When building routes, a page is matched based on the Itemid of the route, the pages route is then prepended to the route being generated. 

The page rule is not directly added to the router, it is the superclass for the component rule.

