<p style="text-align: center; font-size: 11px; color:red; margin-top: 20px;">
	Powered by {{$conf.System.Name}} ver {{$conf.System.Version}}, in {{round((microtime(true) - $request.time) * 1000, 6)}} ms,
	with {{round(memory_get_usage() / 1048576, 3)}} MB memory usage
	{{if isset($mysql)}}, with {{$mysql -> cnt()}} quer{{if $mysql -> cnt() > 1}}ies{{else}}y{{/if}} {{/if}}.
</p>