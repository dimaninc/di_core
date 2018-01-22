# Authentication

## POST /api/auth/login

Log in to the system

query params:

`vm_login`: login/email of user
`vm_password`: password

response:

`ok`: true/false

## GET /api/auth/logout

Log out from the system

query params:

`back`: where to redirect after logging out

no response, just 302 Redirect

## POST /api/auth/reset

Reset password request

query params:

`email`: email of user

response:

`ok`: true/false
`message`: description of error if ok is false

## POST /api/auth/set_password

Set new password request (user should be authenticated)

query params:

`old_password`: old user password
`new_password`: new user password
`new_password2`: repeated new user password

response:

`ok`: true/false
`message`: description of error if ok is false
