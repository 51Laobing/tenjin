/*
  TenJin Call Center System v3.0 Copyright (C) 2016 By TenJin Inc.
  by QQ: 2403378726
*/

#include <stdio.h>
#include <stdlib.h>
#include <stdbool.h>
#include <string.h>
#include <ctype.h>
#include <unistd.h>
#include <signal.h>
#include <hiredis/hiredis.h>
#include "core.h"

bool is_task_exist(redisContext *db, const char *task_id);
bool is_file_exist(const char *file);
void filter(char *number, size_t len); 

int main(int argc, char *argv[]) {
    signal(SIGCHLD, SIG_IGN);
    daemon(0, 0);
    
    if (argc == 3) {
        redisContext *db = redis("127.0.0.1", 6379, NULL, 0);
        if (!db) {
            return 0;
        }

        
        char *task_id = argv[1];
        /* check task exists */
        if (!is_task_exist(db, task_id)) {
            if (db) {
                redisFree(db);
            }
            return 0;
        }
        
        char *file = argv[2];
        /* check file exists */
        if (!is_file_exist(file)) {
            if (db) {
                redisFree(db);
            }
            return 0;
        }
        
        FILE *fp = NULL;
        fp = fopen(file, "r");

        if (fp == NULL) {
            if (db) {
                redisFree(db);
            }
            return 0;
        }


        redisReply *reply = NULL;
        
        int i = 0;
        char buff[1024] = "";
        while (fgets(buff, 1024, fp) != NULL) {
            filter(buff, sizeof(buff));
            if (is_number(buff, strlen(buff))) {
                reply = redisCommand(db, "LPUSH data.%s %s", task_id, buff);
                if (reply != NULL) {
                    freeReplyObject(reply);
                    reply = NULL;
                    i++;
                }
            }
        }
        
        // write number total
        reply = redisCommand(db, "HSET task.%s total %d", task_id, i);
        
        if (reply != NULL) {
            freeReplyObject(reply);
        }
        
        if (db) {
            redisFree(db);
        }

        /* close file */
        fclose(fp);
        remove(file);
    }

    return 0;
}

bool is_task_exist(redisContext *db, const char *task_id) {
    if (!db || task_id == NULL || *task_id == '\0') {
        return false;
    }

    bool task = false;
    redisReply *reply = NULL;

    reply = redisCommand(db, "EXISTS task.%s", task_id);
    if (reply != NULL) {
        if (reply->type == REDIS_REPLY_INTEGER) {
            if (reply->integer == 1) {
                task = true;
            }
        }
        freeReplyObject(reply);
    }

    return task;
}

bool is_file_exist(const char *file) {
    if (file == NULL || *file == '\0') {
        return false;
    }
    
    FILE *fp = NULL;
    if ((fp = fopen(file, "r")) != NULL) {
        fclose(fp);
        return true;
    }
    
    return false;
}

void filter(char *number, size_t len) {
    int i;
    for (i = 0; i < len; i++) {
        if ((number[i] == '\r') || (number[i] == '\n')) {
            number[i] = '\0';
            break;
        }
    }
    number[i] = '\0';
    return;
}
