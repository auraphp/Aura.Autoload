<?php
/**
 * 
 * This file is part of the Aura project for PHP.
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace Aura\Autoload;

/**
 * 
 * An SPL autoloader adhering to [PSR-0](http://groups.google.com/group/php-standards/web/psr-0-final-proposal).
 * 
 * @package aura.autoload
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
     * The directories containing a particular class and its parents.
     * 
     * @var array
     * 
     */
    protected $dirs = array();
    
    /**
     * 
     * Registers this autoloader with SPL.
     * 
     * @param string $config_mode The config mode of the Aura environment.
     * In 'test' mode, the autoloader looks for classes in the package tests
     * directories.
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
     * Adds a directory path for a class name prefix.
     * 
     * @param string $name The class name prefix, e.g. 'Aura\Framework\\' or
     * 'Zend_'.
     * 
     * @param string $path The absolute path leading to the classes for that
     * prefix, e.g. '/path/to/system/package/Aura.Framework-dev/src'.
     * 
     * @return void
     * 
     */
    public function addPrefix($name, $path)
    {
        $this->prefixes[$name][] = rtrim($path, DIRECTORY_SEPARATOR);
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
     * Adds a file path for an exact class name.
     * 
     * @param string $name The exact class name.
     * 
     * @param string $path The file path to that class.
     * 
     * @return void
     * 
     */
    public function addClass($name, $path)
    {
        $this->classes[$name] = $path;
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
            
            // strip the prefix from the class ...
            $spec = substr($class, $len);
            
            // ... and go through each of the paths for the prefix
            foreach ($paths as $path) {
                
                // convert the remaining spec to a file name
                $file = $path . DIRECTORY_SEPARATOR . $this->classToFile($spec);
                
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
    
    // find the dir for a namespace or class directory, 
    // or the containing dir for a class.
    // 
    // because we can have multiple locations for prefixes,
    // does that mean we can have multiple directories?
    public function findDir($spec)
    {
        // make sure we don't have a trailing namespace separator
        $spec = rtrim($spec, '\\');
        
        // do we already have a dir for the spec?
        if (isset($this->dirs[$spec])) {
            return $this->dirs[$spec];
        }
        
        // is the spec a known class?
        $class = $this->find($spec);
        if ($class) {
            $this->dirs[$spec][] = dirname($class);
            return $this->dirs[$spec];
        }
        
        // assume that it's a directory for a namespace or class,
        // not a class file itself.
        $this->dirs[$spec] = false;
        
        // go through each of the path prefixes for classes
        foreach ($this->prefixes as $prefix => $paths) {
            
            // remove the trailing namespace separator
            $prefix = rtrim($prefix, '\\');
            
            // get the length of the prefix
            $len = strlen($prefix);
            
            // does the prefix match?
            if (substr($spec, 0, $len) != $prefix) {
                // no
                continue;
            }
            
            // strip the prefix from the spec ...
            $spec = substr($spec, $len);
            
            // ... and go through each of the paths for the prefix
            foreach ($paths as $path) {
                
                // add the remaining spec to the path
                $dir = $path . DIRECTORY_SEPARATOR . $spec;
                
                // does it exist?
                if (is_dir($dir)) {
                    // found it; retain in dir map
                    $this->dirs[$spec][] = $dir;
                }
            }
        }
        
        // done
        return $this->dirs[$spec];
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
