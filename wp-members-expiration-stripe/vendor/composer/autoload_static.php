<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6c65767b0fd430c8402d176bc2b4f037
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Stripe\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit6c65767b0fd430c8402d176bc2b4f037::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit6c65767b0fd430c8402d176bc2b4f037::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}