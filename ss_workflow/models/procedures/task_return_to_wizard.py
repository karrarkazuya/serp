# -*- coding: utf-8 -*-

from odoo import models, fields, api, exceptions


class ssw_task_return_to_wizard(models.TransientModel):
    _name = 'ssw.proc.task.return.to.wizard'
    _description = 'Task Return To Wizard'

    task_id            = fields.Many2one('ssw.proc.tasks', string='Task', required=True)
    return_to_task_id  = fields.Many2one('ssw.proc.tasks', string='Return to Task', required=True, domain="return_to_task_domain", context={'task_return_wizard': True})
    return_to_task_domain = fields.Binary(compute='_compute_return_to_task_domain')
    reason             = fields.Text(string='Return Reason', required=True)

    @api.depends('task_id')
    def _compute_return_to_task_domain(self):
        for rec in self:
            if rec.task_id:
                tasks = self.env['ssw.proc.tasks'].sudo().search([
                    ('procedure_id', '=', rec.task_id.procedure_id.id),
                    ('task_sequance', '<', rec.task_id.task_sequance),
                    ('deleted', '=', False),
                    ('state', '!=', 'skipped'),
                    ('id', '!=', rec.task_id.id),
                ])
                rec.return_to_task_domain = [('id', 'in', tasks.ids)]
            else:
                rec.return_to_task_domain = [('id', '=', 0)]

    def action_confirm(self):
        self.ensure_one()
        if not self.reason or not self.reason.strip():
            raise exceptions.ValidationError("Please provide a reason for returning this task.")
        if not self.return_to_task_id:
            raise exceptions.ValidationError("Please select a task to return to.")
        self.task_id.sudo()._do_return_to_task(
            target_task=self.return_to_task_id.sudo(),
            reason=self.reason.strip()
        )
        return {'type': 'ir.actions.act_window_close'}
