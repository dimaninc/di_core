# Cabinet API

## POST /api/cabinet/set_password

Set new password request (user should be authenticated)

query params:

	old_password: (string) old user password
	new_password: (string) new user password
	new_password2: (string) repeated new user password

response:

	ok: (bool)
	message: (string) description of error if ok is false
