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
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: administrator_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.administrator_roles (
    id integer NOT NULL,
    administrator_id integer NOT NULL,
    role_id integer NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: administrator_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.administrator_roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: administrator_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.administrator_roles_id_seq OWNED BY public.administrator_roles.id;


--
-- Name: administrators; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.administrators (
    id integer NOT NULL,
    username character varying(200) NOT NULL,
    password_hash character varying(255) NOT NULL,
    active boolean NOT NULL,
    name character varying(100),
    created timestamp without time zone DEFAULT now() NOT NULL,
    CONSTRAINT username_length CHECK ((char_length((username)::text) >= 4))
);


--
-- Name: administrators_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.administrators_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: administrators_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.administrators_id_seq OWNED BY public.administrators.id;


--
-- Name: event_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.event_types (
    id smallint NOT NULL,
    event_type character varying(255) NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    description text
);


--
-- Name: events; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.events (
    id bigint NOT NULL,
    event_type_id smallint NOT NULL,
    title character varying(255) NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    administrator_id integer,
    ip_address character varying(50),
    resource character varying(100),
    request_method character varying(20),
    payload jsonb,
    referer character varying(100),
    session_id character varying(100)
);


--
-- Name: permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.permissions (
    id integer NOT NULL,
    title character varying(255) NOT NULL,
    active boolean DEFAULT true NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    description text
);


--
-- Name: permsissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.permsissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: permsissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.permsissions_id_seq OWNED BY public.permissions.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    role character varying(100) NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: roles_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles_permissions (
    id integer NOT NULL,
    role_id integer NOT NULL,
    permission_id integer NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL
);


--
-- Name: roles_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_permissions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_permissions_id_seq OWNED BY public.roles_permissions.id;


--
-- Name: event_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.event_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: event_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.event_types_id_seq OWNED BY public.event_types.id;


--
-- Name: events_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.events_id_seq OWNED BY public.events.id;


--
-- Name: administrator_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrator_roles ALTER COLUMN id SET DEFAULT nextval('public.administrator_roles_id_seq'::regclass);


--
-- Name: administrators id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrators ALTER COLUMN id SET DEFAULT nextval('public.administrators_id_seq'::regclass);


--
-- Name: event_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_types ALTER COLUMN id SET DEFAULT nextval('public.event_types_id_seq'::regclass);


--
-- Name: events id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events ALTER COLUMN id SET DEFAULT nextval('public.events_id_seq'::regclass);


--
-- Name: permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions ALTER COLUMN id SET DEFAULT nextval('public.permsissions_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: roles_permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles_permissions ALTER COLUMN id SET DEFAULT nextval('public.roles_permissions_id_seq'::regclass);


--
-- Name: administrator_roles adm_role_idx; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT adm_role_idx UNIQUE (administrator_id, role_id);


--
-- Name: administrator_roles administrator_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT administrator_roles_pkey PRIMARY KEY (id);


--
-- Name: administrators administrators_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrators
    ADD CONSTRAINT administrators_pkey PRIMARY KEY (id);


--
-- Name: administrators administrators_username_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrators
    ADD CONSTRAINT administrators_username_key UNIQUE (username);


--
-- Name: permissions permission_idx; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permission_idx UNIQUE (title);


--
-- Name: permissions permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.permissions
    ADD CONSTRAINT permissions_pkey PRIMARY KEY (id);


--
-- Name: roles_permissions role_perm_idx; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles_permissions
    ADD CONSTRAINT role_perm_idx UNIQUE (role_id, permission_id);


--
-- Name: roles_permissions roles_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles_permissions
    ADD CONSTRAINT roles_permissions_pkey PRIMARY KEY (id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_role_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_role_key UNIQUE (role);


--
-- Name: event_types event_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.event_types
    ADD CONSTRAINT event_types_pkey PRIMARY KEY (id);


--
-- Name: events events_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_pkey PRIMARY KEY (id);


--
-- Name: events_title_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX events_title_idx ON public.events USING btree (title);


--
-- Name: administrator_roles administrator_roles_administrator_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT administrator_roles_administrator_id_fkey FOREIGN KEY (administrator_id) REFERENCES public.administrators(id);


--
-- Name: administrator_roles administrator_roles_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT administrator_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id);


--
-- Name: events fk_admin_id; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT fk_admin_id FOREIGN KEY (administrator_id) REFERENCES public.administrators(id);


--
-- Name: roles_permissions roles_permissions_permission_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles_permissions
    ADD CONSTRAINT roles_permissions_permission_id_fkey FOREIGN KEY (permission_id) REFERENCES public.permissions(id);


--
-- Name: roles_permissions roles_permissions_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles_permissions
    ADD CONSTRAINT roles_permissions_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id);


--
-- Name: events events_event_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.events
    ADD CONSTRAINT events_event_type_fkey FOREIGN KEY (event_type_id) REFERENCES public.event_types(id);


--
-- PostgreSQL database dump complete
--

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
49	Database Tables View	t	2018-10-09 19:21:53.471802	\N
50	Database Tables Insert	t	2018-10-09 19:21:53.471802	\N
51	Database Tables Update	t	2018-10-09 19:21:53.471802	\N
52	Database Tables Delete	t	2018-10-09 19:21:53.471802	\N
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
94	1	49	2018-10-09 19:23:38.085565
95	1	50	2018-10-09 19:23:38.085565
96	1	51	2018-10-09 19:23:38.085565
97	1	52	2018-10-09 19:23:38.085565
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
-- Name: event_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.event_types_id_seq', 10, true);


--
-- PostgreSQL database dump complete
--

