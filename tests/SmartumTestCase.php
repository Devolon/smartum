<?php

namespace Devolon\Smartum\Tests;

use Devolon\Payment\DevolonPaymentServiceProvider;
use Devolon\Smartum\DevolonSmartumServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class SmartumTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        putenv('IS_SMARTUM_AVAILABLE=true');
        parent::setUp();

        $this->artisan('migrate:fresh');

        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email')->unique();
            $table->string('password');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
    }

    protected function getEnvironmentSetUp($app)
    {
        $config = $app->make(Repository::class);

        $config->set('auth.defaults.provider', 'users');

        if (($userClass = $this->getUserClass()) !== null) {
            $config->set('auth.providers.users.model', $userClass);
        }
        $config->set('auth.guards.api', ['provider' => 'users']);

        $app['config']->set('database.default', 'testbench');
        $app['config']->set('app.debug', 'true');

        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function getPackageProviders($app)
    {
        return [DevolonPaymentServiceProvider::class, DevolonSmartumServiceProvider::class];
    }

    /**
     * Get the Eloquent user model class name.
     *
     * @return string|null
     */
    protected function getUserClass(): string
    {
        return User::class;
    }
}
