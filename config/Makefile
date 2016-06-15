install:
	/bin/cp -vf postgresql.conf /var/lib/pgsql/data
	/bin/cp -vf pg_hba.conf /var/lib/pgsql/data
	/usr/bin/chown postgres:postgres /var/lib/pgsql/data/postgresql.conf
	/usr/bin/chown postgres:postgres /var/lib/pgsql/data/pg_hba.conf
	/bin/cp -vf pgbouncer.ini /etc
	/usr/bin/mkdir -pv /etc/pgbouncer
	/bin/cp -vf userlist.txt /etc/pgbouncer
	/bin/cp -vf pgbouncer.conf /etc/tmpfiles.d
