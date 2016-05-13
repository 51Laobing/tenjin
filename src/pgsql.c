#include <stdio.h>
#include <libpq-fe.h>

int main(int argc, char *argv[]) {
    char *conninfo = NULL;
    PGconn *conn = NULL;
    PGresult *res = NULL;

    int nFields;
    int i, j;
  
    conninfo = argv[1];
  
    conn = PQconnectdb(conninfo);

    if (PQstatus(conn) != CONNECTION_OK) {
        fprintf(stderr, "connect to postgresql failed: %s\n", PQerrorMessage(conn));
        PQfinish(conn);
        return 0;
    }

    res = PQexec(conn, "select count(status) from agents where name in(select agent from tiers where queue='2@queue') and status = 'Available' union all select count(state) from agents where name in(select agent from tiers where queue='2@queue') and status = 'Available' and state = 'Waiting'");
    if (PQresultStatus(res) != PGRES_TUPLES_OK) {
        fprintf(stderr, "select command failed: %s\n", PQerrorMessage(conn));
        PQclear(res);
        PQfinish(conn);
        return 0;      
    }

    nFields = PQnfields(res);
    for (i = 0; i < nFields; i++) {
        if (i == 8) {
            printf("%-20s", PQfname(res, i));
        } else {
            printf("%-15s", PQfname(res, i));
        }
    }
    printf("\n");

    for (i = 0; i < PQntuples(res); i++) {
        printf("%-15s", PQgetvalue(res, i, 0));
        printf("%-15s", PQgetvalue(res, i, 1));
        printf("%-15s", PQgetvalue(res, i, 2));
        printf("%-15s", PQgetvalue(res, i, 3));
        printf("%-15s", PQgetvalue(res, i, 4));
        printf("%-15s", PQgetvalue(res, i, 5));
        printf("%-15s", PQgetvalue(res, i, 6));
        printf("%-15s", PQgetvalue(res, i, 7));
        printf("%-20s", PQgetvalue(res, i, 8));
        printf("%-15s", PQgetvalue(res, i, 9));
        printf("\n");
    }
  
    PQclear(res);
    PQfinish(conn);
  
    return 0;
}
