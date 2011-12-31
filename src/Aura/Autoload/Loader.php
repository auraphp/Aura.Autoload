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
 * An SPL autoloader adhering to [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)
 * and <https://wiki.php.net/rfc/splclassloader>.
 * 
 */
class Loader
{
    /**
     * 
     * Operational mode where no exceptions are thrown under error conditions.
     *
     * @const
     * 
     */
    const MODE_SILENT = 0;
    
    /**
     * 
     * Operatinal mode where an exception is thrown when a class file is not
     * found.
     *
     * @const
     * 
     */
    const MODE_NORMAL = 1;
    
    /**
     * 
     * Operatinal mode where an exception is thrown when a class file is not
     * found, or if after loading the file the class is still not declared.
     *
     * @const
     * 
     */
    const MODE_DEBUG = 2;
    
    /**
     * 
     * Classes and interfaces loaded by the autoloader; the key is the class
     * name and the value is the file name.
     * 
     * @var array
     * 
     */
    protected $loaded = [];
    
    /**
     * 
     * A map of class name prefixes to directory paths.
     * 
     * @var array
     * 
     */
    protected $paths = [];
    
    /**
     * 
     * A map of exact class names to their file paths.
     * 
     * @var array
     * 
     */
    protected $classes = [];
    
    /**
     * 
     * The operational mode.
     * 
     * @var int
     * 
     */
    protected $mode = self::MODE_NORMAL;
    
    /**
     * 
     * A log of paths that have been tried during load(), for debug use.
     * 
     * @var array
     * 
     */
    protected $tried_paths = [];
    
    /**
     * 
     * Sets the autoloader operational mode.
     *
     * @param int $mode Autoloader operational mode.
     * 
     */
    public function setMode($mode)
    {
        $this->mode = $mode;
    }
    
    /**
     * 
     * Is the autoloader in debug mode?
     *
     * @param int $mode Autoloader operational mode.
     * 
     */
    public function isDebug()
    {
        return $this->mode == self::MODE_DEBUG;
    }
    
    /**
     * 
     * Is the autoloader in silent mode?
     *
     * @param int $mode Autoloader operational mode.
     * 
     */
    public function isSilent()
    {
        return $this->mode == self::MODE_SILENT;
    }
    
    /**
     * 
     * Registers this autoloader with SPL.
     * 
     * @return void
     * 
     */
    public function register($prepend = false)
    {
        spl_autoload_register([$this, 'load'], true, (bool) $prepend);
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
        spl_autoload_unregister([$this, 'load']);
    }
    
    /**
     * 
     * Adds a directory path for a class name prefix.
     * 
     * @param string $prefix The class name prefix, e.g. 'Aura\Framework\\' or
     * 'Zend_'.
     * 
     * @param array|string $paths The directory path leading to the classes 
     * with that prefix, e.g. `'/path/to/system/package/Aura.Framework-dev/src'`.
     * Note that the classes must thereafter be in subdirectories of their 
     * own, e.g. `'/Aura/Framework/'.
     * 
     * @return void
     * 
     */
    public function add($prefix, $paths)
    {
        foreach ((array) $paths as $path) {
            $this->paths[$prefix][] = rtrim($path, DIRECTORY_SEPARATOR);
        }
    }
    
    /**
     * 
     * Sets all class name prefixes and their paths. This overwrites the
     * existing mappings.
     * 
     * Paths can a string or an array. For example:
     * 
     *      $loader->setPaths([
     *          'Zend_'=> '/path/to/zend/library',
     *          'Aura\\' => [
     *              '/path/to/project/Aura.Router/src/',
     *              '/path/to/project/Aura.Di/src/'
     *          ],
     *          'Vendor\\' => [
     *              '/path/to/project/Vendor.Package/src/',
     *          ],
     *          'Symfony\Component' => 'path/to/Symfony/Component',
     *      ]);
     * 
     * @param array $paths An associative array of class names and paths.
     * 
     */
    public function setPaths(array $paths = [])
    {
        foreach ($paths as $key => $val) {
            $this->add($key, $val);
        }
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
     * Sets the exact file path for an exact class name.
     * 
     * @param string $class The exact class name.
     * 
     * @param string $path The file path to that class.
     * 
     * @return void
     * 
     */
    public function setClass($class, $path)
    {
        $this->classes[$class] = $path;
    }
    
    /**
     * 
     * Sets all file paths for all class names; this overwrites all previous
     * exact mappings.
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
     * @param string $spec The class or interface to load.
     * 
     * @return void
     * 
     * @throws Exception\NotReadable when the file for the class or 
     * interface is not found.
     * 
     */
    public function load($spec)
    {
        // is the class already loaded?
        if ($this->isDeclared($spec)) {
            if ($this->isDebug()) {
                // yes, throw an exception
                throw new Exception\AlreadyLoaded($spec);
            } else {
                // no, just return
                return;
            }
        }
        
        // find the class file
        $file = $this->find($spec);
        if (! $file) {
            // did not find it.  do we care?
            if ($this->isSilent()) {
                // no, return without notice
                return;
            } else {
                // yes, throw an exception
                $message = $spec . PHP_EOL
                         . implode(PHP_EOL, $this->tried_paths);
                throw new Exception\NotReadable($message);
            }
        }
        
        // load the file
        require $file;
        
        // is the class declared now?
        if (! $this->isDeclared($spec)) {
            // no. do we care?
            if ($this->isDebug()) {
                // yes, throw an exception
                throw new Exception\NotDeclared($spec);
            } else {
                // no, return without notice
                return;
            }
        }
        
        // done!
        $this->loaded[$spec] = $file;
    }
    
    /**
     * 
     * Tells if a class or interface exists.
     * 
     * @param string $spec The class or interface.
     * 
     * @return bool
     * 
     */
    protected function isDeclared($spec)
    {
        return class_exists($spec, false)
            || interface_exists($spec, false);
    }
    
    /**
     * 
     * Finds the path to a class or interface using the class prefix paths and 
     * include-path.
     * 
     * @param string $spec The class or interface to find.
     * 
     * @return The absolute path to the class or interface.
     * 
     */
    public function find($spec)
    {
        // does the class exist in the explicit class map?
        if (isset($this->classes[$spec])) {
            return $this->classes[$spec];
        }
        
        $this->tried_paths = [];
        
        // go through each of the path prefixes
        foreach ($this->paths as $prefix => $paths) {
            
            // get the length of the prefix
            $len = strlen($prefix);
            
            // does the prefix match?
            if (substr($spec, 0, $len) != $prefix) {
                // no
                continue;
            }
            
            // .. convert class name to file name ...
            $ctf = $this->classToFile($spec);
            
            // ... and go through each of the paths for the prefix
            foreach ($paths as $i => $path) {
                
                // track which paths we have tried
                $this->tried_paths[] = "#{$i}: {$path}";
                
                // convert the remaining spec to a file name
                $file = $path . DIRECTORY_SEPARATOR . $ctf;
                
                // does it exist?
                if (is_readable($file)) {
                    // found it; retain in class map
                    return $file;
                }
            }
        }
        
        // fall back to the include path
        $file = $this->classToFile($spec);
        try {
            $obj = new \SplFileObject($file, 'r', true);
        } catch (\RuntimeException $e) {
            $k = count($this->tried_paths);
            $this->tried_paths[] = "#{$k}" . get_include_path();
            return false;
        }
        $path = $obj->getRealPath();
        return $path;
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
