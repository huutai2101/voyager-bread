<?php

namespace TPlus\VoyagerBread;

use Illuminate\Support\ServiceProvider;
use TPlus\VoyagerBread\Commands\VoyagerBreadCommand;

class VoyagerBreadServiceProvider extends ServiceProvider{
	
	public function boot() {

	}

	public function register() {
		$this->commands(VoyagerBreadCommand::class);
	}
}
