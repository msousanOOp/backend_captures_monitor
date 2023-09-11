<?php

include __DIR__."/vendor/autoload.php";

var_dump(\Composer\InstalledVersions::getRootPackage()['pretty_version']);