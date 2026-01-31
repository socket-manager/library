PHP_ARG_ENABLE(socketsfd, whether to enable socketsfd,
[  --enable-socketsfd     Enable socketsfd extension], no)

if test "$PHP_SOCKETSFD" != "no"; then
  PHP_ADD_EXTENSION_DEP(socketsfd, sockets)
  PHP_NEW_EXTENSION(socketsfd, socketsfd.c, $ext_shared)
fi
