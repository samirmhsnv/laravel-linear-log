<?php

namespace SamirMhsnv\LaravelLinearIssues;

use Illuminate\Support\ServiceProvider;
use Monolog\Logger;
use Monolog\Logger as MonologLogger;
use Monolog\LogRecord;

class LinearIssuesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/linear-issues.php', 'linear-issues');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/linear-issues.php' => config_path('linear-issues.php'),
            ], 'linear-issues-config');
        }

        $this->app->make('log')->extend('linear', function ($app, array $config): MonologLogger {
            $packageConfig = $app['config']->get('linear-issues', []);
            $mergedConfig = array_replace($packageConfig, $config);
            $level = $mergedConfig['level'] ?? 'error';
            $bubble = (bool) ($mergedConfig['bubble'] ?? true);

            $logger = new MonologLogger($mergedConfig['name'] ?? 'linear');

            if (class_exists(LogRecord::class)) {
                $logger->pushHandler(new LinearIssueHandler($mergedConfig, $level, $bubble));
            } else {
                $resolvedLevel = Logger::toMonologLevel($level);
                $monolog2Level = is_int($resolvedLevel) ? $resolvedLevel : $resolvedLevel->value;

                $logger->pushHandler(new LinearIssueHandlerMonolog2($mergedConfig, $monolog2Level, $bubble));
            }

            return $logger;
        });
    }
}
