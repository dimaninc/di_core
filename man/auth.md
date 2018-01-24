# Authentication API

## POST /api/auth/login

Log in to the system

query params:

	vm_login: (string) login/email of user
	vm_password: (string) password
	vm_remember: (bool) remember password

response:

	ok: (bool)

## GET /api/auth/logout

Log out from the system

query params:

	back: (string) where to redirect after logging out

no response, just 302 Redirect

## POST /api/auth/reset

Reset password request

query params:

	email: (string) email of user

response:

	ok: (bool)
	message: (string) description of error if ok is false

## POST /api/auth/enter_new_password

Set new password on password reset

query params:

	email: (string) user email
	key: (string) user's hash key
	password: (string) user password
	password2: (string) repeated user password

response:

	ok: (bool)
	message: (string) description of error if ok is false
