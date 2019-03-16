<?php
namespace Silex\ControllerProviderInterface;

interface ControllerProviderInterface {
    public function connect(Application $app);
}