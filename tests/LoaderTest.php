<?php
namespace aura\autoload;

/**
 * Test class for Loader.
 */
class LoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     */
    public function testRegister()
    {
        $autoloader = new Loader;
        $autoloader->register();
        $functions = spl_autoload_functions();
        list($object, $method) = array_pop($functions);
        $this->assertType('aura\autoload\Loader', $object);
        $this->assertSame('load', $method);
    }
    
    /**
     */
    public function testLoadAndLoaded()
    {
        $class = 'aura\autoload\MockAutoloadClass';
        $autoloader = new Loader;
        $autoloader->addPath('aura\autoload\\', __DIR__);
        $autoloader->load($class);
        
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($class, $actual);
        
        $expect = array(
            $class => __DIR__ . DIRECTORY_SEPARATOR . 'MockAutoloadClass.php',
        );
        
        $actual = $autoloader->getLoaded();
        $this->assertSame($expect, $actual);
    }
    
    /**
     * @expectedException \aura\autoload\Exception_NotFound
     */
    public function testLoadMissing()
    {
        $autoloader = new Loader;
        $autoloader->addPath('aura\autoload\\', __DIR__);
        $autoloader->load('aura\autoload\NoSuchClass');
    }
    
    /**
     * @expectedException \aura\autoload\Exception_NotFound
     */
    public function testLoadNotInIncludePath()
    {
        $autoloader = new Loader;
        $autoloader->load('NoSuchClass');
    }
    
    /**
     */
    public function testLoad_classWithoutNamespace()
    {
        // set a temp directory
        $dir = AURA_TEST_RUN_SYSTEM_DIR . DIRECTORY_SEPARATOR
             . 'tmp' . DIRECTORY_SEPARATOR
             . 'test' . DIRECTORY_SEPARATOR 
             . 'aura.autoload.Loader';
        
        @mkdir($dir, 0777, true);
        
        // add to the include path *just for this test*
        $old_include_path = ini_get('include_path');
        ini_set('include_path', $old_include_path . PATH_SEPARATOR . $dir);
        
        // write a test file to the temp location
        $code = "<?php class ClassWithoutNamespace {}";
        $name = "$dir/ClassWithoutNamespace.php";
        file_put_contents($name, $code);
        
        // autoload it
        $expect = 'ClassWithoutNamespace';
        $autoloader = new Loader;
        $autoloader->addPath('aura\autoload\\', __DIR__);
        
        $autoloader->load($expect);
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($expect, $actual);
        
        // delete the file and directory
        unlink($name);
        rmdir($dir);
        
        // reset to old include path
        ini_set('include_path', $old_include_path);
    }
    
    public function testSetPathAndGetPaths()
    {
        $autoloader = new Loader;
        $autoloader->addPath('Foo_', '/path/to/Foo');
        $actual = $autoloader->getPaths();
        $expect = array('Foo_' => array('/path/to/Foo'));
        $this->assertSame($expect, $actual);
    }
    
    public function testClassToFile()
    {
        $autoloader = new Loader;
        
        $list = array(
            'Foo'                       => 'Foo.php',
            'Foo_Bar'                   => 'Foo/Bar.php',
            'foo\\Bar'                  => 'foo/Bar.php',
            'foo_bar\\Baz'              => 'foo_bar/Baz.php',
            'foo_bar\\Baz_Dib'          => 'foo_bar/Baz/Dib.php',
            'foo_bar\\baz_dib\\Zim_Gir' => 'foo_bar/baz_dib/Zim/Gir.php',
        );
        
        foreach ($list as $class => $expect) {
            $actual = $autoloader->classToFile($class);
            $expect = str_replace('/', DIRECTORY_SEPARATOR, $expect);
            $this->assertSame($expect, $actual);
        }
    }
}
