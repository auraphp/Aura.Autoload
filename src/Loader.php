<?php
/**
 * 
 * This file is part of the Aura project for PHP.
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
namespace aura\autoload;

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
    protected $paths = array();
    
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
     * @param string $name The class name prefix, e.g. 'aura\framework\\' or
     * 'Zend_'.
     * 
     * @param string $path The absolute path leading to the classes for that
     * prefix, e.g. '/path/to/system/package/aura.framework-dev/src'.
     * 
     * @return void
     * 
     */
    public function addPath($name, $path)
    {
        $this->paths[$name][] = rtrim($path, DIRECTORY_SEPARATOR);
    }
    
    /**
     * 
     * Returns the list of all class name prefixes and their paths.
     * 
     * @return array
     * 
     */
    public function getPaths()
    {
        return $this->paths;
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
     * @throws Exception_NotFound when the file for the class or 
     * interface is not found.
     * 
     */
    public function load($class)
    {
        // go through each of the prefixes
        foreach ($this->paths as $prefix => $paths) {
            
            // get the length of the prefix
            $len = strlen($prefix);
            
            // if the prefix matches ...
            if (substr($class, 0, $len) == $prefix) {
                
                // ... strip the prefix from the class ...
                $spec = substr($class, $len);
            
                // ... and go through each of the paths for the prefix
                foreach ($paths as $path) {
                    
                    // convert the remaining spec to a file name
                    $file = $path . DIRECTORY_SEPARATOR . $this->classToFile($spec);
                    
                    // does it exist?
                    if (file_exists($file)) {
                        // load it, and done
                        $this->loadClassFile($class, $file);
                        return;
                    }
                }
            }
        }
        
        // fall back to the include path
        $file = $this->classToFile($class);
        try {
            $obj = new \SplFileObject($file, 'r', true);
            $this->loadClassFile($class, $obj->getRealPath());
        } catch (\RuntimeException $e) {
            throw new Exception_NotFound($file);
        }
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
    
    /**
     * 
     * Loads a class file, and retains a mapping for it in `$loaded`.
     * 
     * @param string $class The class to load.
     * 
     * @param string $file The file where the class resides.
     * 
     * @return void
     * 
     */
    protected function loadClassFile($class, $file)
    {
        require $file;
        $this->loaded[$class] = $file;
    }
}
