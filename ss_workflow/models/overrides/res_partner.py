from odoo import models, fields, exceptions, api

class ResPartner(models.Model):
    _inherit = 'res.partner'
    
    ssw_ticket_count = fields.Integer(string="Tickets count", compute="_compute_ssw_tickets_count")
    ssw_ticket_ids = fields.One2many('ssw.tickets', 'optional_partner_id', string="Tickets")
    
    @api.depends('ssw_ticket_ids')
    def _compute_ssw_tickets_count(self):
        for partner in self:
            partner.ssw_ticket_count = self.env['ssw.tickets'].sudo().search_count([('optional_partner_id', '=', partner.id), ('deleted', '=', False)])
        
    def action_get_tickets_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'Tickets',
            'view_mode': 'list,form',
            'res_model': 'ssw.tickets',
            'domain': [('optional_partner_id', '=', self.id)],
            'context': {
                'default_optional_partner_id': self.id,
                'create': True,
            }
        }
        
    ssw_task_count = fields.Integer(string="tasks count", compute="_compute_ssw_tasks_count")
    ssw_task_input_ids = fields.One2many('ssw.procedures', 'optional_partner_id', string="tasks")
    
    @api.depends('ssw_task_input_ids')
    def _compute_ssw_tasks_count(self):
        for partner in self:
            partner.ssw_task_count = self.env['ssw.procedures'].sudo().search_count([('optional_partner_id', '=', partner.id), ('deleted', '=', False)])
        
    def action_get_procedures_record(self):
        self.ensure_one()
        return {
            'type': 'ir.actions.act_window',
            'name': 'procedures',
            'view_mode': 'list,form',
            'res_model': 'ssw.procedures',
            'domain': [('optional_partner_id', '=', self.id)],
            'context': {
                'default_optional_partner_id': self.id,
                'create': True,
            }
        }

    