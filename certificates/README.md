Insert keys for simple_oauth module here. You can generate keys with:

openssl genrsa -out private.key 2048
openssl rsa -in private.key -pubout > public.key

Then add the file paths to the simple_oauth configuration.

Or use the "Generate keys" button on the module settings page (preferred method).
