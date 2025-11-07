<?php

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Extension\SandboxExtension;
use Twig\Markup;
use Twig\Sandbox\SecurityError;
use Twig\Sandbox\SecurityNotAllowedTagError;
use Twig\Sandbox\SecurityNotAllowedFilterError;
use Twig\Sandbox\SecurityNotAllowedFunctionError;
use Twig\Source;
use Twig\Template;
use Twig\TemplateWrapper;

/* modules/contrib/commerce/modules/order/templates/commerce-order-total-summary.html.twig */
class __TwigTemplate_da7c9732362c611114c6217fce0bc21a extends Template
{
    private Source $source;
    /**
     * @var array<string, Template>
     */
    private array $macros = [];

    public function __construct(Environment $env)
    {
        parent::__construct($env);

        $this->source = $this->getSourceContext();

        $this->parent = false;

        $this->blocks = [
        ];
        $this->sandbox = $this->extensions[SandboxExtension::class];
        $this->checkSecurity();
    }

    protected function doDisplay(array $context, array $blocks = []): iterable
    {
        $macros = $this->macros;
        // line 21
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\Core\Template\TwigExtension']->attachLibrary("commerce_order/total_summary"), "html", null, true);
        yield "
<div";
        // line 22
        yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, ($context["attributes"] ?? null), "html", null, true);
        yield ">
  ";
        // line 23
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["totals"] ?? null), "subtotal", [], "any", false, false, true, 23)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 24
            yield "    <div class=\"order-total-line order-total-line__subtotal\">
      <span class=\"order-total-line-label\">";
            // line 25
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Subtotal"));
            yield " </span><span class=\"order-total-line-value\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\commerce_price\TwigExtension\PriceTwigExtension']->formatPrice(CoreExtension::getAttribute($this->env, $this->source, ($context["totals"] ?? null), "subtotal", [], "any", false, false, true, 25)), "html", null, true);
            yield "</span>
    </div>
  ";
        }
        // line 28
        yield "  ";
        $context['_parent'] = $context;
        $context['_seq'] = CoreExtension::ensureTraversable(CoreExtension::getAttribute($this->env, $this->source, ($context["totals"] ?? null), "adjustments", [], "any", false, false, true, 28));
        foreach ($context['_seq'] as $context["_key"] => $context["adjustment"]) {
            // line 29
            yield "    <div class=\"order-total-line order-total-line__adjustment order-total-line__adjustment--";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, \Drupal\Component\Utility\Html::getClass(CoreExtension::getAttribute($this->env, $this->source, $context["adjustment"], "type", [], "any", false, false, true, 29)), "html", null, true);
            yield "\">
      <span class=\"order-total-line-label\">";
            // line 30
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, CoreExtension::getAttribute($this->env, $this->source, $context["adjustment"], "label", [], "any", false, false, true, 30), "html", null, true);
            yield " </span><span class=\"order-total-line-value\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\commerce_price\TwigExtension\PriceTwigExtension']->formatPrice(CoreExtension::getAttribute($this->env, $this->source, $context["adjustment"], "amount", [], "any", false, false, true, 30)), "html", null, true);
            yield "</span>
    </div>
  ";
        }
        $_parent = $context['_parent'];
        unset($context['_seq'], $context['_key'], $context['adjustment'], $context['_parent']);
        $context = array_intersect_key($context, $_parent) + $_parent;
        // line 33
        yield "  ";
        if ((($tmp = CoreExtension::getAttribute($this->env, $this->source, ($context["totals"] ?? null), "total", [], "any", false, false, true, 33)) && $tmp instanceof Markup ? (string) $tmp : $tmp)) {
            // line 34
            yield "    <div class=\"order-total-line order-total-line__total\">
      <span class=\"order-total-line-label\">";
            // line 35
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->renderVar(t("Total"));
            yield " </span><span class=\"order-total-line-value\">";
            yield $this->extensions['Drupal\Core\Template\TwigExtension']->escapeFilter($this->env, $this->extensions['Drupal\commerce_price\TwigExtension\PriceTwigExtension']->formatPrice(CoreExtension::getAttribute($this->env, $this->source, ($context["totals"] ?? null), "total", [], "any", false, false, true, 35)), "html", null, true);
            yield "</span>
    </div>
  ";
        }
        // line 38
        yield "</div>
";
        $this->env->getExtension('\Drupal\Core\Template\TwigExtension')
            ->checkDeprecations($context, ["attributes", "totals"]);        yield from [];
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTemplateName(): string
    {
        return "modules/contrib/commerce/modules/order/templates/commerce-order-total-summary.html.twig";
    }

    /**
     * @codeCoverageIgnore
     */
    public function isTraitable(): bool
    {
        return false;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getDebugInfo(): array
    {
        return array (  100 => 38,  92 => 35,  89 => 34,  86 => 33,  75 => 30,  70 => 29,  65 => 28,  57 => 25,  54 => 24,  52 => 23,  48 => 22,  44 => 21,);
    }

    public function getSourceContext(): Source
    {
        return new Source("", "modules/contrib/commerce/modules/order/templates/commerce-order-total-summary.html.twig", "/var/www/html/web/modules/contrib/commerce/modules/order/templates/commerce-order-total-summary.html.twig");
    }
    
    public function checkSecurity()
    {
        static $tags = ["if" => 23, "for" => 28];
        static $filters = ["escape" => 21, "t" => 25, "commerce_price_format" => 25, "clean_class" => 29];
        static $functions = ["attach_library" => 21];

        try {
            $this->sandbox->checkSecurity(
                ['if', 'for'],
                ['escape', 't', 'commerce_price_format', 'clean_class'],
                ['attach_library'],
                $this->source
            );
        } catch (SecurityError $e) {
            $e->setSourceContext($this->source);

            if ($e instanceof SecurityNotAllowedTagError && isset($tags[$e->getTagName()])) {
                $e->setTemplateLine($tags[$e->getTagName()]);
            } elseif ($e instanceof SecurityNotAllowedFilterError && isset($filters[$e->getFilterName()])) {
                $e->setTemplateLine($filters[$e->getFilterName()]);
            } elseif ($e instanceof SecurityNotAllowedFunctionError && isset($functions[$e->getFunctionName()])) {
                $e->setTemplateLine($functions[$e->getFunctionName()]);
            }

            throw $e;
        }

    }
}
