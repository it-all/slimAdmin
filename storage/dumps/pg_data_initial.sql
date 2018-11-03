--
-- PostgreSQL database dump
--

-- Dumped from database version 10.5 (Ubuntu 10.5-1.pgdg16.04+1)
-- Dumped by pg_dump version 10.5 (Ubuntu 10.5-1.pgdg16.04+1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: event_types; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.event_types (id, event_type, created, description) FROM stdin;
1	debug	2017-09-09 07:24:17.407514	Detailed debug information.
2	info	2017-09-09 07:26:34.734512	Interesting events. Examples: User logs in.
3	notice	2017-09-09 07:27:14.758275	Normal but significant events.
5	warning	2017-09-09 07:28:41.128122	Exceptional occurrences that are not errors. Examples: Use of deprecated APIs, poor use of an API, undesirable things that are not necessarily wrong.
6	error	2017-09-09 07:29:17.325642	Runtime errors that do not require immediate action but should typically be logged and monitored.
7	critical	2017-09-09 07:29:57.66948	Critical conditions. Example: Application component unavailable, unexpected exception.
8	alert	2017-09-09 07:31:37.612442	Action must be taken immediately. Example: Entire website down.
9	emergency	2017-09-09 07:32:03.820578	System is unusable.
10	security	2018-10-29 08:59:04.921936	Security violations or possible security threats.
\.


--
-- Data for Name: permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.permissions (id, title, active, created, description) FROM stdin;
35	Permissions Insert	t	2018-10-09 19:18:03.821344	\N
36	Permissions Update	t	2018-10-09 19:18:11.99996	\N
37	Permissions View	t	2018-10-09 19:18:31.570101	\N
38	Permissions Delete	t	2018-10-09 19:18:38.332485	\N
41	Administrators View	t	2018-10-09 19:22:27.806212	\N
42	Administrators Insert	t	2018-10-09 19:22:38.758744	\N
43	Administrators Update	t	2018-10-09 19:22:52.92146	\N
45	Roles View	t	2018-10-09 19:23:38.085565	\N
46	Roles Insert	t	2018-10-09 19:23:47.34572	\N
47	Roles Update	t	2018-10-09 19:23:57.519934	\N
48	Roles Delete	t	2018-10-09 19:24:07.431625	\N
44	Administrators Delete	t	2018-10-09 19:23:10.012963	\N
39	Events View	t	2018-10-09 19:21:53.471802	\N
\.


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles (id, role, created) FROM stdin;
1	owner	2018-05-01 00:00:00
\.


--
-- Data for Name: roles_permissions; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.roles_permissions (id, role_id, permission_id, created) FROM stdin;
90	1	46	2018-10-18 18:05:47.285558
91	1	47	2018-10-18 18:46:07.299393
93	1	48	2018-10-18 19:02:14.028606
39	1	35	2018-10-09 19:18:03.821344
40	1	36	2018-10-09 19:18:11.99996
42	1	37	2018-10-09 19:18:31.570101
43	1	38	2018-10-09 19:18:38.332485
46	1	39	2018-10-09 19:21:53.471802
51	1	41	2018-10-09 19:22:27.806212
53	1	42	2018-10-09 19:22:38.758744
55	1	43	2018-10-09 19:22:52.92146
57	1	44	2018-10-09 19:23:10.012963
59	1	45	2018-10-09 19:23:38.085565
\.


--
-- Name: permsissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.permsissions_id_seq', 63, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 138, true);


--
-- Name: roles_permissions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_permissions_id_seq', 109, true);


--
-- Name: system_event_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.system_event_types_id_seq', 10, true);


--
-- PostgreSQL database dump complete
--

