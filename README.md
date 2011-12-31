Aura Autoload
=============

The Aura Autoload package provides a [PSR-0](http://groups.google.com/group/php-standards/web/psr-0-final-proposal) compliant SPL autoloader implementation for PHP. It also matches the interface proposed at <https://wiki.php.net/rfc/splclassloader>.


Include-Path Usage
==================

Create an instance of the `Loader` and register it with SPL.

    <?php
    $loader = require '/path/to/Aura.Autoload/scripts/instance.php';
    $loader->register();

The `Loader` will now look for PSR-0 compliant class names in the include-path, and throw an `Exception\NotDeclared` if it cannot find one.


Class Prefix Usage
==================

You can tell the `Loader` to search particular paths for classes with specific prefixes. The prefixes can be the older PEAR-style class prefixes, or the newer PHP 5.3 formal namespace prefixes.
    
    <?php
    // look for all Vendor_* classes in this path:
    $loader->add('Vendor_', '/path/to/lib');
    
    // look for Vendor\Package classes in this path:
    $loader->add('Vendor\Package\\', '/path/to/Vendor.Package/src');
    
    // additionally, e.g. in testing modes, also look for Vendor\Package
    // classes in this path as well:
    $loader->add('Vendor\Package\\', '/path/to/Vendor.Package/tests');

(Note that you should end formal namespace prefixes with a double-backslash, not a single backslash.)

If the `Loader` cannot find a class in the explicit paths for the specific prefix given, it will fall back to looking in the include-path.

Note that the path should point to the top of a PSR-0 compliant directory structure.  For example, this ...

    $loader->add('Vendor\Package\\', '/path/to/Vendor.Package/src');

... assumes a directory structure like this:

    Vendor.Package/
        src/
            Vendor/
                Package/
                    Class.php

You can also set all paths at once as an array.

    $loader->setPaths([
        'Aura\Router\\' => '/path/to/project/Aura.Router/src/',
        'Aura\Di\\'     => '/path/to/project/Aura.Di/src/',
    ]);

Exact Class Usage
=================

You can tell the `Loader` where a specific individual class is located using the `setClass()` method.

    <?php
    // look for the VendorClassName at this location:
    $loader->setClass('VendorClassName', '/path/to/VendorClassName.php');

This allows you to build relatively fast lookup maps of class names to file names.

You can also set all classes at once using the `setClasses()` method:

    <?php
    $loader->setClasses([
        'Vendor\Package\Foo' => '/path/to/Vendor/Package/Foo.php',
        'Vendor\Package\Bar' => '/path/to/Vendor/Package/Bar.php',
        'Vendor\Package\Zim' => '/path/to/Vendor/Package/Zim.php',
    ]);

* * *
