{% macro recursiveNavSection(key, navigationItem) %}
    {% import _self as self %}
    <li>
        {% if navigationItem.link %}
            <a href="{{ path_for(navigationItem.link) }}">{{ key }}</a>
        {% else %}
            {{ key }}
        {% endif %}
        {% if navigationItem.subSections|length %}
            <a href="#" onclick="toggleDisplay(getElementById('{{ key }}'));togglePlusMinus(this);">+</a>
            <ul class="adminNavSubSection" id="{{ key }}">
                {% for key, navigationItem in navigationItem.subSections %}
                    {{ self.recursiveNavSection(key, navigationItem) }}
                {% endfor %}
            </ul>
        {% endif %}
    </li>
{% endmacro %}

{% from _self import recursiveNavSection %}

{% if navigationItems %}
    <ul>
        {% for key, navigationItem in navigationItems %}
            {{ recursiveNavSection(key, navigationItem) }}
        {% endfor %}
    </ul>
{% endif %}