CC = gcc
CFLAGS = -std=gnu99 -Wall -pedantic
INCLUDE = -I /usr/include -I /usr/local/include
ESLPATH = /root/freeswitch-1.6.7/libs/esl

all: upd server

upd: upd.c core.c core.h config.h
	$(CC) $(CFLAGS) $(INCLUDE) -lhiredis upd.c core.c -o upd

server: server.c core.c core.h config.c config.h data.h $(ESLPATH)/.libs/libesl.a
	$(CC) $(CFLAGS) $(INCLUDE) -I $(ESLPATH)/src/include -lm -pthread -lconfig -lhiredis -lpq server.c core.c config.c $(ESLPATH)/.libs/libesl.a -o server

test: test.c config.c config.h $(ESLPATH)/.libs/libesl.a
	gcc -I $(ESLPATH)/src/include -lm -pthread -lconfig test.c config.c $(ESLPATH)/.libs/libesl.a -o test

install:
	/bin/cp -vf upd /var/www
	/bin/cp -vf server /usr/bin
	/bin/chown apache:apache /var/www

clean:
	rm -rf upd server
