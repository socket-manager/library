PHP_ARG_ENABLE(socketsfd, whether to enable socketsfd,
[  --enable-socketsfd     Enable socketsfd extension], no)

if test "$PHP_SOCKETSFD" != "no"; then

  dnl ---- Check if sockets extension header exists ----
  AC_MSG_CHECKING([for ext/sockets support])
  if test -f "`php-config --include-dir`/ext/sockets/php_sockets.h"; then
    AC_MSG_RESULT([yes])
  else
    AC_MSG_ERROR([ext/sockets is required but not found])
  fi

  dnl ---- Force HAVE_SOCKETS so php_socket typedef becomes visible ----
  AC_DEFINE(HAVE_SOCKETS, 1, [Whether sockets extension is available])

  dnl ---- Add include path ----
  PHP_ADD_INCLUDE(`php-config --include-dir`/ext/sockets)

  PHP_NEW_EXTENSION(socketsfd, socketsfd.c, $ext_shared)
fi
