<?php
namespace Aura\Autoload;

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    protected $loader;
    
    protected $base_dir;
    
    protected function setup()
    {
        $this->loader = new Loader;
    }
    
    public function testRegisterAndUnregister()
    {
        $this->loader->register();
        
        $functions = spl_autoload_functions();
        list($actual_object, $actual_method) = array_pop($functions);
        
        $this->assertSame($this->loader, $actual_object);
        $this->assertSame('loadClass', $actual_method);
        
        // now unregister it so we don't pollute later tests
        $this->loader->unregister();
    }
    
    public function testLoadClass()
    {
        $class = 'Aura\Autoload\Foo';

        $this->loader->addPrefix('Aura\Autoload\\', __DIR__);
        
        $expect_file = __DIR__ . DIRECTORY_SEPARATOR . 'Foo.php';
        $actual_file = $this->loader->loadClass($class);
        
        $this->assertSame($expect_file, $actual_file);
        
        // is it actually loaded?
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($class, $actual);
        
        // is it recorded as loaded?
        $expect = [$class => $expect_file];
        $actual = $this->loader->getLoadedClasses();
        $this->assertSame($expect, $actual);
    }
    
    public function testLoadClassMissing()
    {
        $this->loader->addPrefix('Aura\Autoload\\', __DIR__);
        $class = 'Aura\Autoload\MissingClass';
        $this->loader->loadClass($class);
        $loaded = $this->loader->getLoadedClasses();
        $this->assertFalse(isset($loaded[$class]));
    }
    
    public function testAddPrefix()
    {
        // append
        $this->loader->addPrefix('Foo\Bar', '/path/to/foo-bar/tests');
        
        // prepend
        $this->loader->addPrefix('Foo\Bar', '/path/to/foo-bar/src', true);
        
        $actual = $this->loader->getPrefixes();
        $expect = [
            'Foo\Bar\\' => [
                '/path/to/foo-bar/src/',
                '/path/to/foo-bar/tests/',
            ],
        ];
        $this->assertSame($expect, $actual);
    }
    
    public function testSetPrefixes()
    {
        $this->loader->setPrefixes([
            'Foo\Bar' => '/foo/bar',
            'Baz\Dib' => '/baz/dib',
            'Zim\Gir' => '/zim/gir',
        ]);
        
        $actual = $this->loader->getPrefixes();
        $expect = [
            'Foo\Bar\\' => ['/foo/bar/'],
            'Baz\Dib\\' => ['/baz/dib/'],
            'Zim\Gir\\' => ['/zim/gir/'],
        ];
        $this->assertSame($expect, $actual);
    }
    
    public function testLoadExplicitClass()
    {
        $class = 'Aura\Autoload\Bar';
        $file  = __DIR__ . DIRECTORY_SEPARATOR . 'Bar.php';
        $this->loader->setClassFiles([
            $class => $file,
        ]);

        $actual_file = $this->loader->loadClass($class);
        $this->assertSame($file, $actual_file);
        
        // is it actually loaded?
        $classes = get_declared_classes();
        $actual = array_pop($classes);
        $this->assertSame($class, $actual);
        
        // is it recorded as loaded?
        $expect = [$class => $file];
        $actual = $this->loader->getLoadedClasses();
        $this->assertSame($expect, $actual);
    }
    
    public function testLoadExplicitClassMissing()
    {
        $class = 'Aura\Autoload\MissingClass';
        $file  = __DIR__ . DIRECTORY_SEPARATOR . 'MissingClass.php';
        $this->loader->setClassFiles([
            $class => $file,
        ]);

        $this->assertFalse($this->loader->loadClass($class));
        
        $loaded = $this->loader->getLoadedClasses();
        $this->assertFalse(isset($loaded[$class]));
    }
    
    public function testAddClassFiles()
    {
        $series_1 = [
            'FooBar' => '/path/to/FooBar.php',
            'BazDib' => '/path/to/BazDib.php',
        ];
        
        $series_2 = [
            'ZimGir' => '/path/to/ZimGir.php',
            'IrkDoom' => '/path/to/IrkDoom.php',
        ];
        
        $expect = [
            'FooBar' => '/path/to/FooBar.php',
            'BazDib' => '/path/to/BazDib.php',
            'ZimGir' => '/path/to/ZimGir.php',
            'IrkDoom' => '/path/to/IrkDoom.php',
        ];
        
        $this->loader->addClassFiles($series_1);
        $this->loader->addClassFiles($series_2);
        
        $actual = $this->loader->getClassFiles();
        $this->assertSame($expect, $actual);
    }
    
    public function testSetClassFiles()
    {
        $this->loader->setClassFiles([
            'FooBar' => '/path/to/FooBar.php',
            'BazDib' => '/path/to/BazDib.php',
            'ZimGir' => '/path/to/ZimGir.php',
        ]);
        
        $this->loader->setClassFile('IrkDoom', '/path/to/IrkDoom.php');

        $expect = [
            'FooBar' => '/path/to/FooBar.php',
            'BazDib' => '/path/to/BazDib.php',
            'ZimGir' => '/path/to/ZimGir.php',
            'IrkDoom' => '/path/to/IrkDoom.php',
        ];
        
        $actual = $this->loader->getClassFiles();
        $this->assertSame($expect, $actual);
    }
    
    public function testGetDebug()
    {
        $this->loader->addPrefix('Foo\Bar', '/path/to/foo-bar');
        $this->loader->loadClass('Foo\Bar\Baz');
        
        $actual = $this->loader->getDebug();
        
        $expect = [
            'Loading Foo\\Bar\\Baz',
            'No explicit class file',
            'Foo\\Bar\\: /path/to/foo-bar/Baz.php not found',
            'Foo\\: no base dirs',
            'Foo\\Bar\\Baz not loaded',
        ];
        
        $this->assertSame($expect, $actual);
    }
}
