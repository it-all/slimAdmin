--
-- PostgreSQL database dump
--

-- Dumped from database version 10.4 (Ubuntu 10.4-2.pgdg16.04+1)
-- Dumped by pg_dump version 10.4 (Ubuntu 10.4-2.pgdg16.04+1)

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
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: administrator_roles; Type: TABLE; Schema: public; Owner: slimpg
--

CREATE TABLE public.administrator_roles (
    id integer NOT NULL,
    administrator_id bigint NOT NULL,
    role_id integer NOT NULL
);


ALTER TABLE public.administrator_roles OWNER TO slimpg;

--
-- Name: administrator_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: slimpg
--

CREATE SEQUENCE public.administrator_roles_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.administrator_roles_id_seq OWNER TO slimpg;

--
-- Name: administrator_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slimpg
--

ALTER SEQUENCE public.administrator_roles_id_seq OWNED BY public.administrator_roles.id;


--
-- Name: administrators; Type: TABLE; Schema: public; Owner: slimpg
--

CREATE TABLE public.administrators (
    id integer NOT NULL,
    name character varying(100) NOT NULL,
    username character varying(20) NOT NULL,
    password_hash character varying(255) NOT NULL
);


ALTER TABLE public.administrators OWNER TO slimpg;

--
-- Name: administrators_id_seq; Type: SEQUENCE; Schema: public; Owner: slimpg
--

CREATE SEQUENCE public.administrators_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.administrators_id_seq OWNER TO slimpg;

--
-- Name: administrators_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slimpg
--

ALTER SEQUENCE public.administrators_id_seq OWNED BY public.administrators.id;


--
-- Name: system_event_types; Type: TABLE; Schema: public; Owner: slimpg
--

CREATE TABLE public.system_event_types (
    id smallint NOT NULL,
    event_type character varying(255) NOT NULL,
    created timestamp without time zone DEFAULT now() NOT NULL,
    description text
);


ALTER TABLE public.system_event_types OWNER TO slimpg;

--
-- Name: log_types_id_seq; Type: SEQUENCE; Schema: public; Owner: slimpg
--

CREATE SEQUENCE public.log_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.log_types_id_seq OWNER TO slimpg;

--
-- Name: log_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slimpg
--

ALTER SEQUENCE public.log_types_id_seq OWNED BY public.system_event_types.id;


--
-- Name: login_attempts; Type: TABLE; Schema: public; Owner: slimpg
--

CREATE TABLE public.login_attempts (
    id bigint NOT NULL,
    administrator_id bigint,
    username character varying(20),
    ip character varying(100) NOT NULL,
    created timestamp without time zone NOT NULL,
    success boolean NOT NULL
);


ALTER TABLE public.login_attempts OWNER TO slimpg;

--
-- Name: login_attempts_id_seq; Type: SEQUENCE; Schema: public; Owner: slimpg
--

CREATE SEQUENCE public.login_attempts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.login_attempts_id_seq OWNER TO slimpg;

--
-- Name: login_attempts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slimpg
--

ALTER SEQUENCE public.login_attempts_id_seq OWNED BY public.login_attempts.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: slimpg
--

CREATE TABLE public.roles (
    id integer NOT NULL,
    role character varying(100) NOT NULL,
    level smallint NOT NULL,
    CONSTRAINT positive_level CHECK (((level)::double precision > (0)::double precision))
);


ALTER TABLE public.roles OWNER TO slimpg;

--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: slimpg
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.roles_id_seq OWNER TO slimpg;

--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slimpg
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: system_events; Type: TABLE; Schema: public; Owner: slimpg
--

CREATE TABLE public.system_events (
    id bigint NOT NULL,
    event_type smallint NOT NULL,
    title character varying(255) NOT NULL,
    notes text,
    created timestamp without time zone DEFAULT now() NOT NULL,
    administrator_id bigint,
    ip_address character varying(50),
    resource character varying(100),
    request_method character varying(20)
);


ALTER TABLE public.system_events OWNER TO slimpg;

--
-- Name: system_events_id_seq; Type: SEQUENCE; Schema: public; Owner: slimpg
--

CREATE SEQUENCE public.system_events_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.system_events_id_seq OWNER TO slimpg;

--
-- Name: system_events_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: slimpg
--

ALTER SEQUENCE public.system_events_id_seq OWNED BY public.system_events.id;


--
-- Name: administrator_roles id; Type: DEFAULT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrator_roles ALTER COLUMN id SET DEFAULT nextval('public.administrator_roles_id_seq'::regclass);


--
-- Name: administrators id; Type: DEFAULT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrators ALTER COLUMN id SET DEFAULT nextval('public.administrators_id_seq'::regclass);


--
-- Name: login_attempts id; Type: DEFAULT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.login_attempts ALTER COLUMN id SET DEFAULT nextval('public.login_attempts_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Name: system_event_types id; Type: DEFAULT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.system_event_types ALTER COLUMN id SET DEFAULT nextval('public.log_types_id_seq'::regclass);


--
-- Name: system_events id; Type: DEFAULT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.system_events ALTER COLUMN id SET DEFAULT nextval('public.system_events_id_seq'::regclass);


--
-- Name: administrator_roles administrator_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT administrator_roles_pkey PRIMARY KEY (id);


--
-- Name: administrators administrators_pkey; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrators
    ADD CONSTRAINT administrators_pkey PRIMARY KEY (id);


--
-- Name: administrators administrators_username_key; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrators
    ADD CONSTRAINT administrators_username_key UNIQUE (username);


--
-- Name: login_attempts login_attempts_pkey; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.login_attempts
    ADD CONSTRAINT login_attempts_pkey PRIMARY KEY (id);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: roles roles_role_key; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_role_key UNIQUE (role);


--
-- Name: system_event_types system_event_types_pkey; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.system_event_types
    ADD CONSTRAINT system_event_types_pkey PRIMARY KEY (id);


--
-- Name: system_events system_events_pkey; Type: CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.system_events
    ADD CONSTRAINT system_events_pkey PRIMARY KEY (id);


--
-- Name: system_events_title_idx; Type: INDEX; Schema: public; Owner: slimpg
--

CREATE INDEX system_events_title_idx ON public.system_events USING btree (title);


--
-- Name: administrator_roles administrator_roles_administrator_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT administrator_roles_administrator_id_fkey FOREIGN KEY (administrator_id) REFERENCES public.administrators(id);


--
-- Name: administrator_roles administrator_roles_role_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.administrator_roles
    ADD CONSTRAINT administrator_roles_role_id_fkey FOREIGN KEY (role_id) REFERENCES public.roles(id);


--
-- Name: login_attempts administrators_fkey; Type: FK CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.login_attempts
    ADD CONSTRAINT administrators_fkey FOREIGN KEY (administrator_id) REFERENCES public.administrators(id);


--
-- Name: system_events fk_admin_id; Type: FK CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.system_events
    ADD CONSTRAINT fk_admin_id FOREIGN KEY (administrator_id) REFERENCES public.administrators(id);


--
-- Name: system_events system_events_event_type_fkey; Type: FK CONSTRAINT; Schema: public; Owner: slimpg
--

ALTER TABLE ONLY public.system_events
    ADD CONSTRAINT system_events_event_type_fkey FOREIGN KEY (event_type) REFERENCES public.system_event_types(id);


--
-- PostgreSQL database dump complete
--

