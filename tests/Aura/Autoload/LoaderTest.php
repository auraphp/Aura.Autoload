<?php
namespace Aura\Autoload;

/**
 * Test class for Loader.
 */
class LoaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     */
    public function testRegisterAndUnregister()
    {
        $autoloader = new Loader;
        $autoloader->register();
        $functions = spl_autoload_functions();
        list($object, $method) = array_pop($functions);
        $this->assertInstanceOf('Aura\Autoload\Loader', $object);
        $this->assertSame('load', $method);
        
        // now unregister it so we don't pollute later tests
        $autoloader->unregister();
    }
    
    /**
     */
    public function testLoadAndLoaded()
    {
        $class = 'Aura\Autoload\MockAutoloadClass';
        $autoloader = new Loader;
        $autoloader->add('Aura\Autoload\\', dirname(dirname(__DIR__)));
        $autoloader->load($class);
        
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($class, $actual);
        
        $expect = [
            $class => __DIR__ . DIRECTORY_SEPARATOR . 'MockAutoloadClass.php',
        ];
        
        $actual = $autoloader->getLoaded();
        $this->assertSame($expect, $actual);
        // now unregister it so we don't pollute later tests
        $autoloader->unregister();
    }
    
    public function testLoadAlreadyLoaded()
    {
        $class = 'Aura\Autoload\MockAutoloadAlready';
        $autoloader = new Loader;
        
        // load it once under normal mode
        $autoloader->add('Aura\Autoload\\', dirname(dirname(__DIR__)));
        $autoloader->load($class);
        $loaded = $autoloader->getLoaded();
        $this->assertTrue(isset($loaded[$class]));
        
        // load it again under normal mode, should be no error
        $autoloader->load($class);
        
        // load it again under debug mode, we should see a failure
        $autoloader->setMode(Loader::MODE_DEBUG);
        $this->setExpectedException('Aura\Autoload\Exception\AlreadyLoaded');
        $autoloader->load($class);
    }
    
    public function testLoadMissing()
    {
        $autoloader = new Loader;
        $autoloader->add('Aura\Autoload\\', dirname(dirname(__DIR__)));
        $class = 'Aura\Autoload\NoSuchClass';
        
        // missing in silent mode should be nothing
        $autoloader->setMode(Loader::MODE_SILENT);
        $autoloader->load($class);
        $loaded = $autoloader->getLoaded();
        $this->assertFalse(isset($loaded[$class]));
        
        // missing in normal mode should throw exception
        $autoloader->setMode(Loader::MODE_NORMAL);
        $this->setExpectedException('Aura\Autoload\Exception\NotReadable');
        $autoloader->load($class);
    }
    
    public function testLoadUndeclared()
    {
        $autoloader = new Loader;
        $autoloader->add('Aura\Autoload\\', dirname(dirname(__DIR__)));
        
        // undeclared in normal mode should be nothing
        $class = 'Aura\Autoload\MockAutoloadUndeclared1';
        $autoloader->setMode(Loader::MODE_NORMAL);
        $autoloader->load($class);
        $loaded = $autoloader->getLoaded();
        $this->assertFalse(isset($loaded[$class]));
        
        // undeclared in debug mode should throw exception
        $class = 'Aura\Autoload\MockAutoloadUndeclared2';
        $autoloader->setMode(Loader::MODE_DEBUG);
        $this->setExpectedException('Aura\Autoload\Exception\NotDeclared');
        $autoloader->load($class);
    }
    
    /**
     * @expectedException \Aura\Autoload\Exception\NotReadable
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
        $autoloader->add('Aura\Autoload\\', dirname(dirname(__DIR__)));
        
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
    
    public function testAddPrefixAndGetPaths()
    {
        $autoloader = new Loader;
        $autoloader->add('Foo_', '/path/to/Foo');
        $actual = $autoloader->getPaths();
        $expect = ['Foo_' => ['/path/to/Foo']];
        $this->assertSame($expect, $actual);
    }
    
    public function testSetClassAndGetClasses()
    {
        $autoloader = new Loader;
        $autoloader->setClass('FooBar', '/path/to/FooBar.php');
        $actual = $autoloader->getClasses();
        $expect = ['FooBar' => '/path/to/FooBar.php'];
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
        
        $list = [
            'Foo'                       => 'Foo.php',
            'Foo_Bar'                   => 'Foo/Bar.php',
            'foo\\Bar'                  => 'foo/Bar.php',
            'foo_bar\\Baz'              => 'foo_bar/Baz.php',
            'foo_bar\\Baz_Dib'          => 'foo_bar/Baz/Dib.php',
            'foo_bar\\baz_dib\\Zim_Gir' => 'foo_bar/baz_dib/Zim/Gir.php',
        ];
        
        foreach ($list as $class => $expect) {
            $actual = $autoloader->classToFile($class);
            $expect = str_replace('/', DIRECTORY_SEPARATOR, $expect);
            $this->assertSame($expect, $actual);
        }
    }
    
    public function testSetClasses()
    {
        $autoloader = new Loader;
        $expect = [
            'FooBar' => '/path/to/FooBar.php',
            'BazDib' => '/path/to/BazDib.php',
        ];
        
        $autoloader->setClasses($expect);
        $actual = $autoloader->getClasses();
        $this->assertSame($expect, $actual);
    }
    
    public function testSetPaths()
    {
        $class1 = 'Aura\Autoload\Foo\MockAutoloadCliClass';
        $class2 = 'Aura\Autoload\Bar\MockAutoloadRouterClass';
        $autoloader = new Loader;
        $autoloader->setPaths([
            'Aura\Autoload\Foo\\' => dirname(dirname(__DIR__)),
            'Aura\Autoload\Bar\\' => dirname(dirname(__DIR__))
        ]);
        $autoloader->load($class1);
        $autoloader->load($class2);
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($class2, $actual);
        $actual = array_pop($classes);
        $this->assertSame($class1, $actual);
        
        $expect = [
            $class1 => dirname( dirname( __DIR__ ) ) . DIRECTORY_SEPARATOR . 'Aura/Autoload/Foo/MockAutoloadCliClass.php',
            $class2 => dirname( dirname( __DIR__ ) ) . DIRECTORY_SEPARATOR . 'Aura/Autoload/Bar/MockAutoloadRouterClass.php',
        ];
        
        $actual = $autoloader->getLoaded();
        $this->assertSame($expect, $actual);
    }
}
