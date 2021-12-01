Insert keys for simple_oauth module here. 

Use the `Generate keys` button on the module settings page (preferred method).  

Or you can generate keys with

`openssl genrsa -out private.key 2048`  
`openssl rsa -in private.key -pubout > public.key`

and then add the file paths to the simple_oauth configuration.


