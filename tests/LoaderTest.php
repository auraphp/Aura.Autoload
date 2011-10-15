<?php
namespace Aura\Autoload;

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
        $this->assertType('Aura\Autoload\Loader', $object);
        $this->assertSame('load', $method);
    }
    
    /**
     */
    public function testLoadAndLoaded()
    {
        $class = 'Aura\Autoload\MockAutoloadClass';
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
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
    
    public function testLoadAlreadyLoaded()
    {
        $class = 'Aura\Autoload\MockAutoloadAlready';
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
        $autoloader->load($class);
        
        $this->setExpectedException('Aura\Autoload\Exception\AlreadyLoaded');
        $autoloader->load($class);
    }
    
    /**
     * @expectedException \Aura\Autoload\Exception\NotFound
     */
    public function testLoadMissing()
    {
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
        $autoloader->load('Aura\Autoload\NoSuchClass');
    }
    
    /**
     * @expectedException \Aura\Autoload\Exception\NotFound
     */
    public function testLoadNotInIncludePath()
    {
        $autoloader = new Loader;
        $autoloader->load('NoSuchClass');
    }
    
    /**
     */
    public function testLoadClassWithoutNamespace()
    {
        // set a temp directory in the package
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR
             . 'tmp' . DIRECTORY_SEPARATOR
             . 'tests';
        
        @mkdir($dir, 0777, true);
        
        // add to the include path *just for this test*
        $old_include_path = ini_get('include_path');
        ini_set('include_path', $old_include_path . PATH_SEPARATOR . $dir);
        
        // write a test file to the temp location
        $code = "<?php class ClassWithoutNamespace {}";
        $file = "$dir/ClassWithoutNamespace.php";
        file_put_contents($file, $code);
        
        // set an autoloader with paths
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
        
        // autoload it
        $expect = 'ClassWithoutNamespace';
        $autoloader->load($expect);
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($expect, $actual);
        
        // delete the file and directory
        unlink($file);
        rmdir($dir);
        
        // reset to old include path
        ini_set('include_path', $old_include_path);
    }
    
    public function testAddPrefixAndGetPrefixes()
    {
        $autoloader = new Loader;
        $autoloader->addPrefix('Foo_', '/path/to/Foo');
        $actual = $autoloader->getPrefixes();
        $expect = array('Foo_' => array('/path/to/Foo'));
        $this->assertSame($expect, $actual);
    }
    
    public function testSetClassAndGetClasses()
    {
        $autoloader = new Loader;
        $autoloader->setClass('FooBar', '/path/to/FooBar.php');
        $actual = $autoloader->getClasses();
        $expect = array('FooBar' => '/path/to/FooBar.php');
        $this->assertSame($expect, $actual);
    }
    
    public function testLoadExactClass()
    {
        // set a temp directory in the package
        $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR
             . 'tmp' . DIRECTORY_SEPARATOR
             . 'tests';
        
        @mkdir($dir, 0777, true);
        
        // write a test file to the temp location
        $code = "<?php class ClassWithoutNamespaceForSetClass {}";
        $file = "$dir/ClassWithoutNamespaceForSetClass.php";
        file_put_contents($file, $code);
        
        // set an autoloader with paths
        $autoloader = new Loader;
        $expect = 'ClassWithoutNamespaceForSetClass';
        $autoloader->setClass($expect, $file);
        
        // autoload it
        $expect = 'ClassWithoutNamespaceForSetClass';
        $autoloader->load($expect);
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($expect, $actual);
        
        // delete the file and directory
        unlink($file);
        rmdir($dir);
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
    
    public function testFindDir()
    {
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
        $spec = 'Aura\Autoload';
        $actual = $autoloader->findDir($spec);
        $expect = array(__DIR__ . DIRECTORY_SEPARATOR);
        $this->assertSame($expect, $actual);
    }
    
    public function testFindDirContainingClass()
    {
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
        $spec = 'Aura\Autoload\MockAutoloadClass';
        $actual = $autoloader->findDir($spec);
        $expect = array(__DIR__);
        $this->assertSame($expect, $actual);
        
        // find again for coverage of the cached value
        $actual = $autoloader->findDir($spec);
        $this->assertSame($expect, $actual);
    }
    
    public function testFindDirDoesNotExist()
    {
        $autoloader = new Loader;
        $autoloader->addPrefix('Aura\Autoload\\', __DIR__);
        
        $spec = 'aura\nonesuch';
        $actual = $autoloader->findDir($spec);
        $this->assertSame(array(), $actual);
    }
    
    public function testSetClasses()
    {
        $autoloader = new Loader;
        $expect = array(
            'FooBar' => '/path/to/FooBar.php',
            'BazDib' => '/path/to/BazDib.php',
        );
        
        $autoloader->setClasses($expect);
        $actual = $autoloader->getClasses();;
        $this->assertSame($expect, $actual);
    }
}
