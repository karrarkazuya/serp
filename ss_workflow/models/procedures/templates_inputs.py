# -*- coding: utf-8 -*-

from odoo import models, fields, api


class ssw_templates_inputs(models.Model):
    _name = 'ssw.proc.templates.inputs'
    _description = 'task Input'
    _inherit = ['mail.thread', 'mail.activity.mixin']

    name = fields.Char(string='Title')
    
    template_id                   = fields.Many2one('ssw.proc.templates.tasks', string='Template', required=True, tracking=True)
    type                          = fields.Selection([("char", 'Char'), ("int", 'Integer'), ("date", 'Date'), ("datetime", 'Datetime'), ("boolean", 'Boolean'), ("image", 'Image'), ("select", 'Select'), ("model", 'Other'), ("label", 'Label')], default='char', required=True, tracking=True)
    reference_model_name          = fields.Selection([("res.partner", 'Contact'), ("hr.employee", 'Employee'), ("jtfttha.agents", 'FTTH agent'), ("jtfttha.infra.region", 'FTTH region'), ("jtfttha.infra.project", 'FTTH project'), ("jtfttha.infra.exchange", 'FTTH exchange'), ("jtfttha.infra.ring", 'FTTH ring'), ("jtfttha.infra.fdt", 'FTTH FDT'), ("jtfttha.infra.olt", 'FTTH OLT'), ("jtfttha.infra.port", 'FTTH Port'), ("jtwirelessa.agents", 'Wireless agent'), ("jtwirelessa.repeaters", 'Wireless repeater/node'), ("jtwirelessa.repeaters.vlans", 'Wireless vlan'), ("jtwirelessa.repeaters.switches", 'Wireless switch'), ("jtwirelessa.repeaters.ports", 'Wireless port'), ("ssd.document", 'Document'), ("stock.picking", 'Inventory receipt/delivery/internal'), ("ssw.product.line", 'Product Line'), ("hr.expense.sheet", 'Expense'), ("account.payment", 'Payment'), ("account.move", 'Invoice')], default='res.partner', tracking=True)
    allow_multiple_model_values   = fields.Boolean(string='Allow multiple records', default=False, tracking=True)

    inputs_sub                    = fields.One2many('ssw.proc.templates.inputs.subs', 'input_id', string='Subs', tracking=True)
    is_required                   = fields.Boolean(string='Is Required', default=True, tracking=True)
    show_in_other_tasks           = fields.Many2many('ssw.proc.templates.tasks', string='Show in other tasks', tracking=True)

    deleted                       = fields.Boolean(string='Is Deleted', default=False, tracking=True)
    computed_template_id          = fields.Integer(string='Computed Task id', default=0, compute='_set_task_id', store=True)
    computed_parent_template_id   = fields.Integer(string='Computed Task id', default=0, compute='_set_task_parent_id', store=True)

    @api.depends('template_id')
    def _set_task_id(self):
        for record in self:
            record.computed_template_id = record.template_id.id

    @api.depends('template_id')
    def _set_task_parent_id(self):
        for record in self:
            record.computed_parent_template_id = record.template_id.template_id.id

    def unlink(self):
        for record in self:
            record.write({'deleted': True})
        return True
