<?php
/**
 * 
 * This file is part of the Aura project for PHP.
 * 
 * @package Aura.Autoload
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Autoload;

/**
 * 
 * An SPL autoloader adhering to [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md).
 * 
 */
class Loader
{
    /**
     * 
     * Classes and interfaces loaded by the autoloader; the key is the class
     * name and the value is the file name.
     * 
     * @var array
     * 
     */
    protected $loaded = array();
    
    /**
     * 
     * A map of class name prefixes to directory names.
     * 
     * @var array
     * 
     */
    protected $prefixes = array();
    
    /**
     * 
     * A map of exact class names to their file paths.
     * 
     * @var array
     * 
     */
    protected $classes = array();
    
    /**
     * 
     * Registers this autoloader with SPL.
     * 
     * @return void
     * 
     */
    public function register()
    {
        spl_autoload_register(array($this, 'load'));
    }
    
    /**
     * 
     * Unregisters this autoloader from SPL.
     * 
     * @return void
     * 
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'load'));
    }
    
    /**
     * 
     * Adds a directory path for a class name prefix.
     * 
     * @param string $name The class name prefix, e.g. 'Aura\Framework\\' or
     * 'Zend_'.
     * 
     * @param array|string $paths The absolute path leading to the classes for that
     * prefix, e.g. `'/path/to/system/package/Aura.Framework-dev/src'`. Note
     * that the classes must thereafter be in subdirectories of their own, 
     * e.g. `'/Aura/Framework/'.
     * 
     * @return void
     * 
     */
    public function addPrefix($name, $paths)
    {
        foreach ((array) $paths as $path) {
            $this->prefixes[$name][] = rtrim($path, DIRECTORY_SEPARATOR);
        }
    }
    
    /**
     * Add an array of prefixed name spaces
     * 
     * An array of associative name and paths.
     * 
     * paths can also be an array or a string
     * 
     * Eg : 
     * 
     * $loader->addPrefixes(array(
     *      'Zend_'=> '/path/to/zend/library',
     *      'Aura' => array(
     *          '/path/to/project/Aura.Router/src/',
     *          '/path/to/project/Aura.Di/src/'
     *      ),
     *      'Vendor' => array(
     *          '/path/to/project/Vendor.Package/src/',
     *      ),
     *      'Symfony/Component' => 'path/to/Symfony/Component',
     *  ));
     * 
     * @param array $prefixes
     * 
     */
    public function addPrefixes(array $prefixes = array())
    {
        foreach ($prefixes as $name => $paths ) {
            $this->addPrefix($name, $paths);
        }
    }
    
    /**
     * 
     * Returns the list of all class name prefixes and their paths.
     * 
     * @return array
     * 
     */
    public function getPrefixes()
    {
        return $this->prefixes;
    }
    
    /**
     * 
     * Sets the file path for an exact class name.
     * 
     * @param string $name The exact class name.
     * 
     * @param string $path The file path to that class.
     * 
     * @return void
     * 
     */
    public function setClass($name, $path)
    {
        $this->classes[$name] = $path;
    }
    
    /**
     * 
     * Sets all file paths for all class names.
     * 
     * @param array $classes An array of class-to-file mappings where the key 
     * is the class name and the value is the file path.
     * 
     * @return void
     * 
     */
    public function setClasses(array $classes)
    {
        $this->classes = $classes;
    }
    
    /**
     * 
     * Returns the list of exact class names and their paths.
     * 
     * @return array
     * 
     */
    public function getClasses()
    {
        return $this->classes;
    }
    
    /**
     * 
     * Returns the list of classes and interfaces loaded by the autoloader.
     * 
     * @return array An array of key-value pairs where the key is the class
     * or interface name and the value is the file name.
     * 
     */
    public function getLoaded()
    {
        return $this->loaded;
    }
    
    /**
     * 
     * Loads a class or interface using the class name prefix and path,
     * falling back to the include-path if not found.
     * 
     * @param string $class The class or interface to load.
     * 
     * @return void
     * 
     * @throws Exception\NotFound when the file for the class or 
     * interface is not found.
     * 
     */
    public function load($class)
    {
        if (class_exists($class, false)) {
            throw new Exception\AlreadyLoaded($class);
        }
        
        $file = $this->find($class);
        if (! $file) {
            throw new Exception\NotFound($class);
        }
        require $file;
        $this->loaded[$class] = $file;
    }
    
    /**
     * 
     * Finds the path to a class or interface using the prefixes and 
     * include-path.
     * 
     * @param string $class The class or interface to find.
     * 
     * @return The absolute path to the class or interface.
     * 
     */
    public function find($class)
    {
        // does the class exist in the explicit class map?
        if (isset($this->classes[$class])) {
            return $this->classes[$class];
        }
        
        // go through each of the path prefixes
        foreach ($this->prefixes as $prefix => $paths) {
            
            // get the length of the prefix
            $len = strlen($prefix);
            
            // does the prefix match?
            if (substr($class, 0, $len) != $prefix) {
                // no
                continue;
            }
            
            // .. convert class name to file name ...
            $ctf = $this->classToFile($class);
            
            // ... and go through each of the paths for the prefix
            foreach ($paths as $path) {
                
                // convert the remaining spec to a file name
                $file = $path . DIRECTORY_SEPARATOR . $ctf;
                
                // does it exist?
                if (file_exists($file)) {
                    // found it; retain in class map
                    $this->classes[$class] = $file;
                    return $file;
                }
            }
        }
        
        // fall back to the include path
        $name = $this->classToFile($class);
        try {
            $obj = new \SplFileObject($name, 'r', true);
        } catch (\RuntimeException $e) {
            return false;
        }
        $file = $obj->getRealPath();
        $this->classes[$class] = $file;
        return $file;
    }
    
    /**
     * 
     * PSR-0 compliant class-to-file inflector.
     * 
     * @param string $spec The name of the class or interface to load.
     * 
     * @return string The filename version of the class or interface.
     * 
     */
    public function classToFile($spec)
    {
        // look for last namespace separator
        $pos = strrpos($spec, '\\');
        if ($pos === false) {
            // no namespace, class portion only
            $namespace = '';
            $class     = $spec;
        } else {
            // pre-convert namespace portion to file path
            $namespace = substr($spec, 0, $pos);
            $namespace = str_replace('\\', DIRECTORY_SEPARATOR, $namespace)
                       . DIRECTORY_SEPARATOR;
        
            // class portion
            $class = substr($spec, $pos + 1);
        }
        
        // convert class underscores
        $file = $namespace
              . str_replace('_',  DIRECTORY_SEPARATOR, $class)
              . '.php';
        
        // done!
        return $file;
    }
}
