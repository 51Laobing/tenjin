#include <stdio.h>
#include <stdlib.h>
#include <esl.h>
#include "config.h"

int main(void) {
    conf_t conf;
    if (!load_conf_init("config.conf", &conf)) {
        printf("%s", conf.err);
        return 0;
    }
    
    printf("redis_host: %s\n", conf.redis.host);
    printf("redis_port: %d\n", conf.redis.port);
    printf("redis_password: %s\n", conf.redis.password);
    printf("redis_db: %d\n", conf.redis.db);

    printf("pgsql: %s\n", conf.pgsql);

    printf("esl_host: %s\n", conf.esl.host);
    printf("esl_port: %d\n", conf.esl.port);
    printf("esl_password: %s\n", conf.esl.password);
    printf("\n");
    
	esl_handle_t handle = {{0}};

	esl_connect(&handle, "localhost", 8021, NULL, "ClueCon");

	esl_send_recv(&handle, "api status\n\n");

	if (handle.last_sr_event && handle.last_sr_event->body) {
		printf("%s\n", handle.last_sr_event->body);
	} else {
		// this is unlikely to happen with api or bgapi (which is hardcoded above) but prefix but may be true for other commands
		printf("%s\n", handle.last_sr_reply);
	}

	esl_disconnect(&handle);
	
	return 0;
}
